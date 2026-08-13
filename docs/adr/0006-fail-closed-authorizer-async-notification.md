# 6. Autorizador "fail-closed"; notificação sempre assíncrona e best-effort

## Status
Aceito

## Contexto
O desafio descreve dois serviços externos com garantias opostas: o autorizador **precisa** ser
consultado antes de mover dinheiro; a notificação é explicitamente avisada como
"eventualmente instável". Tratá-los da mesma forma seria um erro em ambas as direções.

## Decisão

**Autorizador — fail-closed.** `HttpAuthorizerGateway::authorize()` nunca deixa uma exceção
escapar: timeout, erro de rede, status não-2xx ou corpo inesperado são todos tratados como
"não autorizado" (retorna `false`, loga um warning). `TransferMoneyUseCase` trata "não
autorizado" e "autorizador fora do ar" exatamente da mesma forma — a transferência é recusada.
Um sistema de pagamentos que move dinheiro quando não tem certeza da autorização está errado
por padrão; um que recusa por segurança quando não tem certeza está apenas indisponível.

**Notificação — assíncrona e best-effort.** O envio passa por uma fila (Symfony Messenger,
transporte Doctrine) e só é despachado **depois** que a transação de transferência já deu
commit (`TransferMoneyUseCase::execute()`, fora do `transactional()`). Isso significa:

1. Uma falha na notificação nunca desfaz uma transferência que já aconteceu.
2. `AsyncNotifier::notifyPayeeOfTransfer()` captura qualquer exceção do próprio *dispatch*
   (não só do envio HTTP em si) e apenas loga — esse detalhe existiu porque, na prática, uma
   falha ali (transporte Messenger ausente) chegou a derrubar uma resposta HTTP 201 legítima
   como 500 durante o desenvolvimento; ver commit relacionado ao endurecimento deste método.
3. O consumidor (`NotifyPayeeMessageHandler`, worker dedicado) tem retry com backoff
   exponencial (`config/packages/messenger.yaml`) e cai no transporte `failed` depois de
   esgotar as tentativas — nada é perdido silenciosamente, mas também nada bloqueia o usuário
   esperando a resposta de `POST /transfer`.

## Consequências
- `POST /transfer` responde assim que o dinheiro muda de mãos — sua latência não inclui a
  do serviço de notificação.
- Rastreável: mensagens que falharam ficam no transporte `failed` (`bin/console
  messenger:failed:show`), não desaparecem.
- Trade-off: o payee pode, por alguns segundos, não ter recebido a notificação por push mesmo
  com a transferência já concluída — aceitável para uma notificação, inaceitável para dinheiro.
