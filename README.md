# Passa

Emissor de um cartão corporativo pré-pago: responde às authorizations da rede do cartão, registra captures e cancellations, mantém o ledger da empresa e dos cartões, e mostra tudo num painel para o financeiro e numa área para cada funcionário.

| Documento                                | O que tem                                                   |
| ---------------------------------------- | ----------------------------------------------------------- |
| [`MODEL.md`](MODEL.md)                   | o modelo, as dez decisões, os riscos e o que foi descartado |
| [`DEVELOPMENT.md`](DEVELOPMENT.md)       | anotações do desenvolvimento                                |
| [`docs/CHALLENGE.md`](docs/CHALLENGE.md) | o enunciado do desafio, como veio no template               |

## Stack

PHP 8.4 · Laravel 13 · Filament 5 · Livewire 4 · Tailwind v4 · PostgreSQL · Pest 4 · [`internachi/modular`](https://github.com/InterNACHI/modular)

## Como rodar

Precisa de Docker, PHP 8.4 (com `intl` e `pdo_pgsql`) e Node 24 (`.nvmrc`).

```bash
make env-up        # Postgres, Redis e Mailpit via Docker
composer setup     # dependências, .env, chave, migrations, seed e assets
composer dev       # servidor, fila, logs e Vite
```

| Onde                                  | Quem                                                                                    |
| ------------------------------------- | --------------------------------------------------------------------------------------- |
| `http://127.0.0.1:8000/admin`         | a gestora, `marina@acme.test`                                                           |
| `http://127.0.0.1:8000/login`         | os portadores: `ana@acme.test`, `bruno@acme.test`, `carla@acme.test`, `diego@acme.test` |
| `http://127.0.0.1:8000/api/network/…` | a rede, com requisições assinadas                                                       |

Senha de todos: `password`. O seed cria a Acme com R$ 10.000,00 de saldo e os quatro cartões do enunciado para mock de usuário.

O ledger é append-only, e nem o BD não aceita apagar uma transaction errada. Para voltar ao estado do seed em desenvolvimento, use `php artisan migrate:fresh --seed`.

### Falar com a API da rede na mão

Toda requisição da rede é assinada com HMAC-SHA256 de `"<timestamp>.<corpo>"`, com o `NETWORK_SECRET` do `.env`:

```bash
SECRET=passa-network-dev-secret
TS=$(date +%s)
SIG=$(printf '%s.' "$TS" | openssl dgst -sha256 -hmac "$SECRET" | sed 's/^.* //')

curl http://127.0.0.1:8000/api/network/cards/tok_ana/available \
  -H "X-Network-Timestamp: $TS" -H "X-Network-Signature: sha256=$SIG"
```

Num `POST`, o corpo entra na assinatura depois do ponto, exatamente como é enviado.

## Como testar

```bash
make test          # a suíte inteira, em paralelo
make check         # Rector, Pint e Larastan (nível 6), sem alterar nada
```

| Suíte         | Onde                          | O que cobre                                                                                                |
| ------------- | ----------------------------- | ---------------------------------------------------------------------------------------------------------- |
| `Arch`        | `tests/Arch`                  | os presets do Laravel e a direção das dependências entre os módulos                                        |
| `Feature`     | `app-modules/*/tests/Feature` | cada módulo testa o próprio assunto: contrato e assinatura, decisões, ledger, painel, portador             |
| `Feature`     | `tests/Feature`               | o que cruza módulos: os cenários publicados, o replay embaralhado e o acesso ao painel                     |
| `Concurrency` | `tests/Concurrency`           | processos PHP de verdade, em paralelo, contra o Postgres: authorizations e events disputando o mesmo saldo |

Para rodar um módulo só: `vendor/bin/pest app-modules/ledger/tests`. Os testes usam o banco `passa_test`, configurado no `.env.testing`.

## Arquitetura

Monolito modular. Cada módulo é um pacote Composer em `app-modules/`, e as dependências só descem:

```
network ──► authorization ──► ledger
cardholder, panel-admin ────► ledger
```

| Módulo          | Namespace             | O que faz                                                                    |
| --------------- | --------------------- | ---------------------------------------------------------------------------- |
| `ledger`        | `Passa\Ledger`        | contas, fatos da rede, depósitos, transactions e projeções; `ledger:rebuild` |
| `authorization` | `Passa\Authorization` | regras de recusa e a decisão de cada authorization                           |
| `network`       | `Passa\Network`       | rotas `api/network/*`, assinatura, validação estrita do contrato             |
| `cardholder`    | `Passa\Cardholder`    | `/login` e `/my-card`, em Livewire, Blade e Tailwind                         |
| `panel-admin`   | `Passa\Admin`         | o painel Filament em `/admin`                                                |

O que é compartilhado fica em `app/`: o `User`, os providers e a página de login do painel. O porquê de cada escolha está no `MODEL.md`, seção 1.

## Suposições

Durante o desenvolvimento, após revisões, pesquisas e refatorações, algumas mudanças foram necessárias para adequar o projeto aos padrões que busquei entregar. Cada uma está justificada no `MODEL.md`:

- **A reserva aparece no statement.** Uma authorization aprovada já desconta o limite restante, e cada mensagem gera exatamente uma transaction (Decisão 2).
- **Capture acima do esperado é aceita inteira e sinalizada.** O limite restante pode ficar negativo, mas só por uma cobrança que a rede já fez; novas compras são recusadas até o mês virar (Decisão 3).
- **A compra pertence ao mês da authorization**, no fuso `America/Sao_Paulo`, e todas as transactions dela contam nesse mês (Decisão 7).
- **Event antes da authorization** é aceito e guardado, mas só gera transaction quando a authorization chega (Decisão 6).
- **Reemissão com conteúdo idêntico** responde `200` sem gravar nada; com conteúdo diferente, `409` (Decisão 10).
- **Capture acima do teto por compra é sinalizada** mesmo dentro da margem de 20% da rede: o P2 aparece como problema (Decisão 5).
- **Uma empresa só.** As escritas de dinheiro são serializadas pelo lock na linha da empresa.
