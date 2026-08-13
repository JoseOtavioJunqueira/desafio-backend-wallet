# 2. Dinheiro como inteiro (centavos), nunca float

## Status
Aceito

## Contexto
Ponto flutuante binário (`float`/`double`) não representa exatamente a maioria dos valores
decimais (`0.1 + 0.2 !== 0.3`). Em um sistema que soma e subtrai dinheiro repetidamente, esse
erro de arredondamento se acumula e pode, no limite, criar ou destruir centavos — inaceitável
num sistema de pagamentos.

## Decisão
`App\Domain\ValueObject\Money` guarda internamente um único `int $cents`. Toda operação
(`add`, `subtract`, comparação) opera sobre esse inteiro. A conversão de/para a representação
decimal ("100.50") só acontece nas bordas: ao ler o JSON de entrada (`Money::fromDecimal()`) e
ao serializar a resposta (`(string) $money`).

No banco, a coluna é `integer` (32 bits), não `bigint`. Isso é deliberado: o tipo `bigint` do
Doctrine DBAL sempre retorna PHP `string` (não `int`) na hidratação, para não estourar em
plataformas 32 bits — o que forçaria todo consumidor do value object a lidar com dois tipos
possíveis. Um `integer` de 32 bits comporta até ~21 milhões de reais por carteira/transferência,
o que é mais que suficiente para este desafio. Se o domínio real precisar de mais, é uma troca
de tipo de coluna (`integer` → `bigint`) e um ajuste no construtor de `Money` para aceitar
string — não uma mudança de design.

## Consequências
- Toda a aritmética financeira do sistema é exata.
- `Money` é o único lugar do código que sabe converter entre centavos e reais — nenhum
  controller ou caso de uso faz `* 100` ou `/ 100` na mão.
- Limite documentado (não bigint) é uma escolha de escopo, não um esquecimento.
