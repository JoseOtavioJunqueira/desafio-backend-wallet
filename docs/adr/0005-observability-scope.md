# 5. Observabilidade: logs estruturados + `/health` + contadores, sem stack completa

## Status
Aceito

## Contexto
"Aplicação de conhecimentos de observabilidade" é um dos itens valorizados pelo desafio, mas é
também uma categoria sem fim: logs, métricas, tracing distribuído, dashboards, alertas. Construir
uma stack completa (Prometheus + Grafana + OpenTelemetry + Alertmanager) para uma API de dois
endpoints reais seria desproporcional ao problema e tomaria tempo de partes com mais peso na
avaliação (o próprio fluxo de transferência, testes, modelagem).

## Decisão
Entregar o suficiente para *diagnosticar um incidente*, não uma central de operações:

- **Logs estruturados em JSON** (`config/packages/monolog.yaml`, já no formato do recipe
  oficial) — prontos para qualquer coletor (CloudWatch, Loki, ELK) sem parsing customizado.
- **`GET /health/live`** (o processo está de pé) e **`GET /health/ready`** (Postgres e Redis
  respondem) — o mínimo que um orquestrador (Kubernetes, ECS) precisa para liveness/readiness
  probes.
- **`GET /metrics`** em texto Prometheus, com um único contador (`picpay_transfers_total`,
  por `outcome`) — o número que mais importa neste domínio: quantas transferências completaram
  vs. foram rejeitadas. Implementado com `INCR` atômico no Redis
  (`App\Infrastructure\Metrics\MetricsRecorder`), sem biblioteca de métricas adicional.

## O que fica de fora, deliberadamente
Histogramas de latência, tracing distribuído (OpenTelemetry), dashboards prontos, alertas.
Justificativa: nenhum desses agrega valor sem um sistema real por trás consumindo — métricas
sem consumidor são só código extra para manter. Ponto natural de evolução se este serviço
crescer além de um desafio técnico.
