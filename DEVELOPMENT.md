# Anotações

O que aconteceu durante o desafio, na ordem em que aconteceu: o que travou, o que descobri no caminho e o que ficou para depois. As decisões e os porquês estão no `MODEL.md`; aqui fica o processo.

## Como trabalhei

- **Modelo antes do código.** O primeiro e metade do segundo dia foram só pensando em como estruturar o `MODEL.md`: a base arquitetural, as dez decisões uma a uma e as previsões do P2 e do P3, escritas antes de existir qualquer migration.
- **Um detalhe por commit.** Como a descrição do desafio pedia, cada parte entrou num commit próprio, no padrão de Conventional Commits, com os testes e o `make check` passando antes de commitar.
- **IA como par.** Usei IA para discutir as opções, revisar e escrever código junto em pair-code. Cada decisão foi minha, desde a pequena na arquitetura a padrões de projeto utilizados em aplicações financeiras, e quando discordei da recomendação ficou registrado no `MODEL.md`, seção 5.
- **Provar que o teste pega o erro.** Nos pontos críticos (locks, isolamento entre portadores, dependências entre módulos), depois de o teste passar, eu quebrava o código de propósito para ver o teste falhar. Um teste que nunca falha não prova nada.

## Linha do tempo

| Dia   | O que entrou                                                                                                                           |
| ----- | -------------------------------------------------------------------------------------------------------------------------------------- |
| 06/10 | `MODEL.md`: base arquitetural, as dez decisões, previsões do P2 e do P3, riscos e o que foi descartado                                 |
| 07/10 | schema e seed, a API da rede (assinatura, contrato, authorizations e events), o ledger, as consultas, o rebuild e os testes de cenário |
| 08/10 | o painel Filament, a área do funcionário, a migração para o `internachi/modular` e a documentação                                      |

## Ambiente

Problemas da minha máquina antes de escrever a primeira linha:

- **Porta 5432 ocupada.** Um PostgreSQL 17 nativo, instalado pelo instalador da EDB e iniciado pelo `launchd`, segurava a porta do Postgres do Docker.
- **PHP sem `intl`.** O PHP 8.4 do Homebrew veio sem a extensão. Resolvi instalando a fórmula `php@8.4`.
- **`pecl install redis` falhando** num `mkdir`. A pasta `/opt/homebrew/lib/php/pecl` não existia; criá-la resolveu.
- **Node antigo.** O 22.9 estava abaixo do `^24` que o projeto pede. Resolvi com `nvm use`, que lê o `.nvmrc`.
- **Classmap autoritativo.** O `composer.json` do template usa `classmap-authoritative`, então cada classe nova só existe para o autoload depois de um `composer dump-autoload`. Isso custou um "class does not exist" no seed até eu entender.

## O que descobri implementando

O desafio oferece um grau de complexidade por mim nunca antes visto, mas poucas vezes me diverti fazendo um teste técnico. Usar soluções que somente na teoria havia estudado, padrtÕes diferentes de arquiteutra modular, e ambientação em outro framework o qual não é minha stack principal foram

### O fuso da sessão do Postgres

Os testes de reemissão falharam comparando o `occurred_at` de duas captures idênticas. A regra estava certa: o meu Postgres local roda com `TimeZone = America/Sao_Paulo`, e o Laravel grava os horários sem fuso. Um `15:00:00` voltava do banco com três horas de diferença. Isso teria deslocado o statement e o mês das compras conforme o servidor onde os cenários rodassem. A conexão passou a fixar a sessão em UTC, com um teste de regressão. Está no `MODEL.md`, seções 3 e 5.

### Os testes que provam os locks

Os testes de concorrência rodam processos PHP de verdade em paralelo contra o Postgres. Teste com transação simulada não prova lock.

- **Authorizations.** Com os locks removidos, as 8 authorizations disputando o mesmo limite foram todas aprovadas. Com os locks, o total aprovado nunca passa do limite.
- **Events.** A primeira versão passava mesmo sem o lock da empresa. Todas as requisições eram do mesmo cartão, e o lock do cartão já as serializava, escondendo o problema. Reescrevi com três cartões disputando o saldo da empresa, e sem o lock o teste passou a falhar 3 de 3 vezes (lost update no saldo).

### O lançamento de estorno

Fiz depósitos de teste pelo painel e quis apagá-los. Não deu: o trigger `transactions_append_only` recusa `DELETE` em transactions, inclusive direto no banco. É o comportamento certo. Num sistema real, um depósito errado se corrige com um **lançamento de estorno**, uma transaction nova com os deltas invertidos, que está fora do escopo e anotada como porta aberta no `MODEL.md`. Em desenvolvimento, o único jeito de limpar é `php artisan migrate:fresh --seed`. Nem eu consigo apagar uma transaction.

### Requisito relido a tempo

Pensei em usar componentes do Filament na área do funcionário para ganhar acabamento. Relendo a Etapa 5, o requisito 6 proíbe ("Nenhum componente do Filament nesta área"). A área ficou em Livewire, Blade e Tailwind, e um teste de arquitetura impede que algum componente do Filament entre lá, no PHP ou nas views.

### Livewire expõe todo método público

Na tela do portador, quase deixei um método público que recebia uma compra e devolvia a história dela. No Livewire, todo método público pode ser chamado pelo navegador e devolve o resultado, então ele seria uma porta para um portador ler a compra de outro. A história passou a ser calculada dentro do `render()`, a partir do cartão do usuário logado. Os testes de isolamento tentam chamar esse método e outros, e o Livewire recusa.

## A migração para o `internachi/modular`

No começo, os módulos eram pastas em `app/`. Lendo um repositório público da 3Pontos, vi que eles usam o `internachi/modular`, com cada módulo como um pacote Composer. Migrei o projeto inteiro em sete commits, um módulo por vez, só com `git mv` e troca de namespace. Os mesmos 238 testes passaram em cada passo.

- **Os pacotes mostraram dois ciclos que as pastas escondiam.** Um módulo `accounts` separado importaria o ledger e seria importado por ele, porque o saldo e o limite do mês são projeções do ledger. As mensagens da rede, que estavam em `Network`, eram a entrada das Actions de domínio. As contas foram para o ledger, e as mensagens viraram DTOs do domínio.
- **Detalhes que só apareceram rodando.**
    - Um arquivo de rotas carregado pelo modular não entra no grupo `web` nem no `api` sozinho.
    - O Larastan só conhecia as colunas dos models depois de ler as migrations do módulo.
    - O `env()` no config do módulo precisou ser declarado como pasta de config para o Larastan.
    - Uma view Blade com `@use` do namespace antigo passou pelo script de troca, porque ele só olhava arquivos `.php`.
- **Duas pegadinhas do plugin de arquitetura do Pest.** `expect([A, B])->not->toUse(...)` com uma lista de alvos deixa a violação passar sem avisar, e `not->toUse('Filament')` não pega nada, enquanto `'Filament\Tables'` pega. Só descobri porque quebrei cada regra de propósito e algumas não falharam.

## O que ficou para depois

- **Navegar o statement por mês na área do funcionário.** Hoje a tela mostra o mês corrente e vira sozinha no dia 1º. A ideia é um seletor `‹ setembro | outubro ›` com os meses que têm transactions, sem meses futuros. O mês mudaria só o statement, e o "Disponível agora" continuaria no mês corrente. O `?month=` iria na URL, validado como `AAAA-MM` e preso ao cartão logado.
- **Um comando para reproduzir os cenários no servidor local.** Hoje o banco de desenvolvimento não tem compras, porque os cenários rodam só nos testes, em outro banco. Um `php artisan network:play p2`, mandando as mensagens assinadas para o servidor, deixaria ver o P2 no painel e no `/my-card`.

### Impressões do desafio

O desafio tem um nível de complexidade que eu nunca tinha enfrentado, e poucas vezes me diverti tanto fazendo um teste técnico. Coloquei em prática soluções que até então eu só conhecia na teoria, como event sourcing, lock pessimista e idempotência por constraint única. Pensei o desenho do sistema de ponta a ponta, testei, modularizei, quebrei de propósito e tentei de novo. E, principalmente, me adaptei a um framework que não é a minha stack principal. Saio deste desafio sabendo muito mais do que quando comecei.
