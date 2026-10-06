# MODEL.md

Este arquivo é o coração da sua entrega. Escreva-o durante o desafio, não depois.

## 1. O modelo

Diagrama (Mermaid ou imagem) e, para cada coisa que existe nele, uma linha dizendo por que ela existe.

### Base arquitetural

Quatro escolhas sustentam todo o resto do modelo.

- **Monolito modular.** O código se divide por contexto dentro de `app/`: `Network`, `Authorization`, `Ledger` e `Cards`. A interface (Filament e Livewire) só lê. Uso a linguagem do domínio sem camadas extras: Actions de domínio e Eloquent direto.
- **Event sourcing.** A fonte da verdade é um log append-only, e nada nele sofre `UPDATE` ou `DELETE`. Limite restante, saldo e saldo disponível são **projeções**: atualizadas na mesma transação do banco que grava o fato e reconstruíveis a partir do log.
- **Idempotência por constraint única.** Uma mensagem repetida ou reemitida esbarra num índice único do Postgres e recebe a mesma resposta da primeira vez.
- **Lock pessimista.** Toda escrita que mexe em dinheiro trava a empresa e depois o cartão (`SELECT … FOR UPDATE`), sempre nessa ordem.

```mermaid
flowchart LR
    Rede((Rede)) -->|HTTP assinado| Network
    Network --> Authorization
    Network --> Ledger
    Authorization --> Cards
    Authorization --> Ledger
    UI[Painel e área do funcionário] -->|leitura| Ledger
    UI -->|leitura| Cards
    UI -->|depósito| Ledger
```

| Módulo | Por que existe |
|---|---|
| `Network` | Verifica a assinatura, valida o contrato, grava a mensagem como fato imutável e barra duplicatas. Não conhece regra de negócio. |
| `Authorization` | Aplica os motivos de recusa na ordem do enunciado e grava a decisão uma única vez. |
| `Ledger` | Único módulo que cria transactions e atualiza as projeções. |
| `Cards` | Empresa, cartões, portadores e as regras de cada cartão (MCC bloqueado, teto por compra, bloqueio, limite mensal). |

#### O log

O log guarda três tipos de fato:

1. **As mensagens da rede**, como chegaram.
2. **As decisões do Passa** sobre cada authorization.
3. **As transactions do ledger.**

A decisão precisa estar no log porque ela depende do estado do momento em que a authorization foi processada (Etapa 1, regra 4). Se eu guardasse só as mensagens, reprocessá-las em outra ordem poderia inverter uma aprovação. A decisão é um fato do Passa, e não um valor que se recalcula.

```mermaid
flowchart TB
    M[Mensagens da rede] --> D[Decisões do Passa]
    M --> T[Transactions do ledger]
    D --> T
    T -->|mesma transação do banco| P[(Projeções: limite restante, saldo, saldo disponível)]
    T -.->|ledger:rebuild| P
```

#### Uma mensagem, do começo ao fim

```mermaid
sequenceDiagram
    participant R as Rede
    participant N as Network
    participant DB as Postgres
    R->>N: requisição assinada
    N->>N: assinatura (401) e contrato (422)
    N->>DB: BEGIN; INSERT da mensagem ON CONFLICT DO NOTHING
    alt mensagem já registrada
        N->>DB: lê o resultado gravado
        N-->>R: mesma resposta da primeira vez
    else mensagem nova
        N->>DB: trava empresa, depois cartão (FOR UPDATE)
        N->>DB: grava decisão e transactions, atualiza projeções
        N->>DB: COMMIT
        N-->>R: 200 / 202
    end
```

Quando duas entregas da mesma mensagem chegam juntas, a segunda fica bloqueada no índice único até a primeira fazer commit. Depois ela lê a decisão já gravada e responde igual. O índice único resolve as duplicatas; o lock resolve a disputa de compras diferentes pelo mesmo saldo. Como só existe uma empresa, as escritas de dinheiro ficam serializadas. Aceito isso de propósito: cada transação dura milissegundos e cabe com folga no prazo de 2 segundos da rede.

**Regra de durabilidade:** nenhuma resposta `2xx` sai antes do `COMMIT`.

O que considerei e deixei fora da base está na seção 5.

### Entidades

```mermaid
erDiagram
    COMPANY ||--o{ CARD : "tem"
    USER ||--o| CARD : "porta"
    CARD ||--o{ PURCHASE : "tem"
    PURCHASE ||--o| AUTHORIZATION : "tem"
    PURCHASE ||--o{ CAPTURE : "tem"
    PURCHASE ||--o| CANCELLATION : "tem"
    COMPANY ||--o{ TRANSACTION : "movimenta"
    CARD ||--o{ TRANSACTION : "movimenta"
    PURCHASE ||--o{ TRANSACTION : "origina"

    TRANSACTION {
        string type
        datetime occurred_at
        string reference
        int limit_delta_cents
        int balance_delta_cents
        int held_delta_cents
    }
```

| Entidade | Por que existe |
|---|---|
| `Company` | Dona do saldo. Toda escrita de dinheiro trava esta linha primeiro. |
| `Card` | Limite mensal e regras: MCC bloqueados, teto por compra, bloqueio. Pertence a um portador. |
| `User` | Login da gestora no painel e dos portadores na área do funcionário. |
| `Purchase` | Agrupa tudo o que a rede manda sob o mesmo `authorization_id`. Nasce com a primeira mensagem que citar esse `id`, seja a authorization ou um event. |
| `Authorization` | A mensagem da rede e a decisão do Passa (`decision` e `reason`), gravadas uma única vez. |
| `Capture` | Cada capture recebida, com `sequence` e `final`. Única por compra e `sequence`. |
| `Cancellation` | No máximo uma por compra. |
| `Transaction` | Linha append-only do ledger. Os três deltas dizem o que ela muda no limite do cartão, no saldo e na reserva da empresa. |

As tabelas de mensagem (`Authorization`, `Capture`, `Cancellation`) também guardam o corpo bruto recebido, para auditoria. Como guardar o `id` da rede é a Decisão 9.

## 2. Decisões

As dez decisões do enunciado. Para cada uma: o que você decidiu e o motivo, em duas ou três linhas. Nas que você hesitou, diga também a alternativa que rejeitou e o que pesou. É contra este texto que conferimos o seu sistema nos pontos que o enunciado deixa em aberto.

### Decisão 1 · Como representar authorization, capture, cancellation, compra e transaction

**Mensagens.** Cada tipo de mensagem tem a sua tabela: `authorizations`, `captures` e `cancellations`. Todas ficam ligadas a uma `purchase`, identificada pelo `authorization_id` da rede. A decisão e o `reason` ficam na própria authorization, porque são o fato do Passa sobre aquela mensagem. A compra nasce com a primeira mensagem que a citar. Assim, um event que chega antes da authorization já tem onde morar.

**Transactions.** Uso um ledger único, `transactions`, append-only. Cada linha tem três deltas:

- `limit_delta_cents`: o que a linha muda no limite restante do cartão;
- `balance_delta_cents`: o que muda no saldo da empresa;
- `held_delta_cents`: o que muda na reserva da empresa.

Cada número do sistema é uma soma:

- limite restante = limite mensal + Σ `limit_delta_cents` das compras do cartão no mês;
- saldo = Σ `balance_delta_cents`;
- saldo disponível = saldo − Σ `held_delta_cents`.

O statement do cartão é a coluna `limit_delta_cents` com o saldo corrido, e o statement da empresa é a coluna `balance_delta_cents`.

**Alternativas rejeitadas.**

- **Tabela única de mensagens com `payload jsonb`.** É mais literal como log, mas a unicidade de `(authorization_id, sequence)` dependeria de índices parciais sobre JSON. Com tabelas tipadas, as constraints que barram reemissões (Decisão 10) saem naturais: `captures` única por compra e `sequence`, `cancellations` única por compra.
- **Inbox bruto separado das tabelas tipadas.** Duplicaria dados que as tabelas tipadas já guardam (o corpo bruto vai junto em cada uma).
- **Ledgers separados para cartão e empresa.** Um mesmo fato, como uma capture, viraria duas linhas em tabelas diferentes, que teriam que continuar consistentes entre si. Com uma linha e três deltas, o fato é atômico e o rebuild das projeções é uma soma.

### Decisão 2 · A reserva aparece no statement

Sim. A authorization aprovada gera uma transaction que reserva o valor e já reduz o limite restante. Cada mensagem que mexe em dinheiro gera **exatamente uma** transaction, com o valor líquido em relação à reserva que ela consome. A `reference` é o `id` da mensagem.

| `type` | Origem | `limit_delta` | `balance_delta` | `held_delta` |
|---|---|---|---|---|
| `authorization` | authorization aprovada | −autorizado | 0 | +autorizado |
| `capture` | capture | −(parte da capture que excede a reserva restante) | −capturado | −(reserva consumida) |
| `cancellation` | cancellation | +reserva restante | 0 | −reserva restante |
| `deposit` | depósito no painel | 0 | +depositado | 0 |

Uma authorization recusada não gera transaction, porque não move dinheiro. Ela aparece na história da compra e na lista de recusadas do painel, mas não no statement.

Uma capture que cabe na reserva entra no statement do cartão com `amount_cents` 0. O limite já tinha sido descontado na aprovação, e a capture só troca reserva por cobrança. O painel e a área do funcionário mostram o valor capturado ao lado da linha.

**Alternativas rejeitadas.**

- **Só o capturado no statement.** Para o invariante fechar, o limite restante teria que ignorar as reservas. Logo depois de uma aprovação de 800, a Ana continuaria com 2.000 de limite restante, e uma authorization de 1.500 passaria na regra 5. Isso deixa 2.300 comprometidos num limite de 2.000.
- **Duas linhas por capture (liberação + cobrança).** É mais legível, mas uma mensagem viraria duas transactions com a mesma `reference`, e cada capture encheria o saldo corrido de idas e voltas.

## 3. Riscos e garantias

Os riscos que você identificou neste domínio. Para cada um: o que pode dar errado, o que no seu código impede que aconteça e qual teste prova isso.

## 4. O que eu esperava dos cenários

Antes de implementar, para P2 e P3: o resultado que você espera depois de cada mensagem e por quê, a partir das suas decisões. Depois de rodar: bateu? O que mudou?

## 5. O que mudou e o que foi descartado

Alterações relevantes do modelo ao longo do caminho, com o motivo. E o que o seu primeiro rascunho, ou a IA, propôs e você não aceitou.
