# 4. Verificação de TLS desligada, só para os dois clients dos mocks do desafio

## Status
Aceito

## Contexto
Os dois serviços mock fornecidos pelo desafio (`util.devi.tools`) respondem com um
certificado TLS expirado (confirmado manualmente em 2026-08-12 — `curl` sem `-k` falha com
`certificate has expired`; com `-k`, o serviço responde normalmente). Isso é um problema do
serviço de terceiro, fora do nosso controle, não algo que o código deva silenciosamente
absorver de forma ampla.

## Decisão
`verify_peer: false` / `verify_host: false` são configurados **apenas** nos dois HTTP clients
nomeados que falam com esses mocks (`app.http_client.authorizer`, `app.http_client.notification`
em `config/services.yaml`). O `HttpClient` padrão do framework, usado por qualquer outra
integração futura, continua com verificação de TLS ligada. Isso está documentado no `.env`
ao lado das URLs e nesta ADR — não é uma configuração global escondida em algum lugar.

## Consequências
- Os testes automatizados nunca dependem disso: `config/services_test.yaml` substitui os dois
  gateways por fakes determinísticos, então a suíte de testes não faz chamada de rede real nem
  depende do certificado do mock.
- Em produção contra um serviço real (não o mock do desafio), a expectativa é remover essas
  duas flags — o comentário no `.env` e nesta ADR deixam explícito que é uma exceção pontual,
  não o padrão do projeto.
