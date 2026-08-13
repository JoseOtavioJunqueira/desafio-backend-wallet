# 3. Saldo é uma coluna embutida em `User`, não uma entidade `Wallet` separada

## Status
Aceito (revertido de uma tentativa anterior)

## Contexto
A primeira versão modelava `Wallet` como entidade própria, em relação um-para-um bidirecional
com `User` (`User` como lado inverso via `mappedBy`). Ao testar de verdade contra PostgreSQL
(não só em teoria), `TransferMoneyUseCase` falhava com:

```
SQLSTATE[0A000]: Feature not supported: 7 ERROR: FOR UPDATE cannot be applied to the
nullable side of an outer join
```

Causa raiz: o Doctrine ORM **não consegue** fazer lazy-load do lado inverso de um
one-to-one — ele não tem a chave estrangeira localmente para montar um proxy, então sempre
faz `JOIN` com a tabela do lado dono, mesmo pedindo só o `User`. Ao pedir esse `User` com lock
pessimista (`getByIdForUpdate`), o Doctrine gera um `LEFT JOIN ... FOR UPDATE`, e o PostgreSQL
recusa lock sobre o lado que pode ser nulo de um outer join. Esse não é um bug de configuração
— é uma limitação estrutural do Doctrine com 1:1 bidirecional.

## Decisão
Eliminar a entidade `Wallet`. O saldo (`balance_cents`, `balance_updated_at`) virou um
`#[ORM\Embedded]` de `Money` diretamente em `User`. `User::credit()`/`User::debit()` operam
sobre esse campo.

Isso também é a modelagem de domínio mais honesta: uma carteira, neste sistema, não tem
identidade, ciclo de vida ou consulta independente do seu dono — ela nasce e morre com o
usuário, sempre um-para-um, nunca é buscada sozinha. Não é um agregado; é um atributo.
Separá-la em entidade própria era indireção sem ganho real de expressividade.

## Consequências
- `getByIdForUpdate()` agora gera `SELECT ... FROM users WHERE id = ? FOR UPDATE` — uma única
  tabela, sem join, sem restrição do PostgreSQL. Coberto por
  `tests/Integration/Persistence/ConcurrentTransferLockTest.php`.
- Se um dia a carteira precisar de identidade própria (múltiplas carteiras por usuário,
  moedas diferentes, histórico de saldo como série temporal), isso volta a justificar uma
  entidade — mas nesse ponto o requisito também mudou, não é mais "a mesma modelagem com um
  join a mais".
