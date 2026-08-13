# 1. Monolito modular em arquitetura hexagonal (não microsserviços)

## Status
Aceito

## Contexto
O desafio descreve um domínio pequeno e coeso — usuários, carteiras e um único fluxo crítico
de transferência — mas a lista de "diferenciais" menciona explicitamente arquiteturas como
microsserviços, CQRS e event-sourcing. É tentador usar o desafio como vitrine para essas
técnicas, mas isso teria um custo real: mais infraestrutura, mais latência de rede entre
serviços, mais pontos de falha, para um domínio que hoje cabe inteiro na cabeça de uma pessoa.

## Decisão
Construir um **monolito modular** com arquitetura hexagonal (ports & adapters) em quatro
camadas:

- `src/Domain` — entidades (`User`, `Transaction`), value objects (`Money`, `Document`),
  regras de negócio puras (`TransferPolicy`), interfaces de repositório. Não depende de mais
  nada no projeto.
- `src/Application` — casos de uso (`TransferMoneyUseCase`, `RegisterUserUseCase`,
  `DepositMoneyUseCase`) e as portas (interfaces) que eles precisam de fora:
  `AuthorizerGatewayInterface`, `NotifierInterface`, `TransactionManagerInterface`,
  `PasswordHasherInterface`. Depende só de `Domain`.
- `src/Infrastructure` — implementações concretas das portas: Doctrine (persistência), Symfony
  HttpClient (autorizador), Messenger (notificação assíncrona), Redis (idempotência, cache).
- `src/Http` — controllers finos, DTOs de request e o listener de exceções. Traduz HTTP em
  chamadas de caso de uso e volta.

A dependência aponta sempre para dentro (`Http`/`Infrastructure` → `Application` → `Domain`),
nunca o contrário — é essa direção que permite trocar Doctrine por outra coisa, ou o autorizador
mock por um real, sem tocar em uma linha de regra de negócio.

## Consequências
- Positivo: cada camada é testável isoladamente (ver `tests/Unit` vs `tests/Integration` vs
  `tests/Functional`); a regra de negócio não sabe que Postgres ou Redis existem.
- Positivo: a estrutura já deixa claro *onde* um futuro corte para microsserviços aconteceria
  (cada porta é um candidato natural a virar uma chamada de rede), sem pagar esse custo agora.
- Trade-off consciente: para um domínio deste tamanho, um framework tradicional
  Controller→Service→Repository teria menos arquivos e chegaria ao mesmo resultado funcional
  mais rápido. A camada extra de `Application`/portas se paga a partir do momento em que o
  domínio cresce ou ganha um segundo adapter por porta (ex.: um segundo autorizador, um segundo
  canal de notificação) — não antes disso. Ponto de discussão válido em entrevista.

Ver também: [0002](0002-money-as-integer-cents.md), [0003](0003-wallet-is-not-an-entity.md).
