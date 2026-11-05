# Capability Match Core

Versão de desenvolvimento **0.1.0** — biblioteca PHP original da **Lofrano Tecnologia**, sob licença MIT.

Associa itens que exigem uma vaga a destinos compatíveis com capacidade finita. O objetivo é lexicográfico: **maximizar a quantidade atendida e, depois, minimizar a soma dos custos inteiros não negativos de preferência**. O resultado inclui associações reproduzíveis, IDs não atendidos, um corte que comprova cardinalidade máxima e potenciais viáveis em todo o grafo residual. Funciona offline, sem Composer, modelo, API, persistência ou telemetria.

[English](README.md) · [Contrato preciso](docs/contract.md) · [Projeto](docs/design.md) · [Site oficial](https://lofranotecnologia.com.br) · [Repositório](https://github.com/martinlofranodeoliveira/capability-match-core)

## Instalação, testes e demonstração

Obtenha o arquivo-fonte do repositório e extraia-o. Na raiz do projeto, com **PHP 8.4 CLI de 64 bits** e as extensões padrão JSON e SPL:

```sh
php -r 'if (PHP_INT_SIZE !== 8 || !extension_loaded("json") || !extension_loaded("SPL")) { exit(1); } echo "Runtime OK\n";'
php tests/run.php
php -d zend.assertions=-1 -d assert.active=0 tests/run.php
php bin/capability-match.php demo --assert
php bin/capability-match.php demo --json
```

Não há dependências a instalar, arquivo de lock, autoloader gerado ou acesso à rede. Inclua `autoload.php` na aplicação. O executor de testes usa verificações explícitas, não `assert()`, e termina com código diferente de zero se houver falhas.

A demonstração verificada imprime exatamente:

```text
DEMO_OK assigned=2 cost=1
```

Os equipamentos `a`, `b` e as portas de teste `p`, `q` são sintéticos. Escolher `a -> p` de forma gulosa bloqueia `b`. O algoritmo residual retorna `a -> q` e `b -> p`. A opção `--json` emite um objeto completo com `problem`, `result` e `verification`, incluindo certificado e contagens determinísticas de inspeções. Não utiliza capturas de tela nem serviço externo.

## Exemplo de API

```php
<?php
declare(strict_types=1);
require 'autoload.php';

use function Lofrano\CapabilityMatch\solve;
use function Lofrano\CapabilityMatch\verifyCertificate;

$problem = [
    'items' => [['id' => '01'], ['id' => '1']],
    'destinations' => [['id' => 'porta-teste', 'capacity' => 2]],
    'compatibilityEdges' => [
        ['item' => '01', 'destination' => 'porta-teste', 'cost' => 3],
        ['item' => '1', 'destination' => 'porta-teste', 'cost' => 0],
    ],
];
$result = solve($problem['items'], $problem['destinations'], $problem['compatibilityEdges']);
if ($result['status'] !== 'Optimal') {
    throw new RuntimeException($result['status']);
}
$checked = verifyCertificate($problem, $result);
if ($checked['status'] !== 'Verified') {
    throw new RuntimeException($checked['status']);
}
echo json_encode(['assigned' => $result['cardinality'], 'cost' => $result['cost']], JSON_THROW_ON_ERROR), "\n";
```

Saída:

```json
{"assigned":2,"cost":3}
```

As funções retornam arrays com um campo `status`, não valores booleanos. Confira esse campo antes de ler dados de sucesso. `new Lofrano\CapabilityMatch\Solver()` oferece `solve()`; `new Lofrano\CapabilityMatch\Verifier()` oferece `verify()`. As instâncias não guardam estado entre chamadas. Os dados do chamador não são modificados nem vinculados por referência ao resultado.

## Contrato resumido

- As entradas são listas de registros com campos exatos, não arrays PHP indexados por ID. IDs são strings UTF-8 válidas, não vazias, de até 128 bytes. `01` e `1` são distintos. Não há normalização Unicode; caracteres de controle e NUL são permitidos como parte de IDs opacos.
- Limites estruturais padrão e máximos absolutos: 128 itens, 64 destinos, 4096 compatibilidades, custo 1.000.000 por aresta e capacidade 128 por destino. O chamador pode reduzi-los.
- Custos, capacidades e limites precisam ser inteiros nativos. Booleanos, floats e strings numéricas são rejeitados. IDs duplicados no mesmo domínio, pares duplicados e referências desconhecidas são rejeitados antes da alocação do grafo residual.
- `relaxationBudget` tem padrão 20.000.000 e máximo 50.000.000. Toda visita algorítmica a uma aresta é cobrada, mesmo com capacidade zero ou sem melhoria de distância. Construção e extração também são cobradas. A validação estrutural, limitada pelos máximos fixos, é separada. Cada chamada pública de verificação recebe orçamento próprio.
- `BudgetExceeded` retorna somente `status`, `message` e `inspections`: **não retorna associação parcial, certificado ou alegação de inviabilidade**. A demonstração com orçamento zero falha assim. Capacidade negativa retorna antes `InvalidCapacity`.
- IDs e compatibilidades são ordenados por bytes. A fila desempata explicitamente por distância, vértice e ordem de inserção. Permutar as coleções de entrada mantém a saída de `json_encode()` byte a byte, com as mesmas opções de codificação e limites.
- Certificados são evidência matemática relativa à entrada, não assinaturas nem hashes da entrada. Verifique contra o problema real; o verificador recalcula totais e reconstrói o grafo sem confiar em resumos fornecidos.

Exemplo com orçamento explícito:

```php
$result = solve($items, $destinations, $edges, ['relaxationBudget' => 100000]);
```

O [contrato detalhado](docs/contract.md), em inglês, define todos os campos, erros e regras de contagem.

## Justificativa do projeto

Caminhos aumentantes mínimos sucessivos, com arestas reversas, permitem desfazer uma escolha anterior. Potenciais inteiros permitem buscas de Dijkstra. Ao final, Bellman-Ford limitado por orçamento e com superorigem implícita produz potenciais para **todos** os componentes residuais, inclusive os inalcançáveis a partir da origem. O verificador reconstrói de forma independente as duas direções de cada aresta original a partir da entrada e da associação.

Os testes incluem exatamente 729 problemas de dois itens e dois destinos, combinando arestas ausentes/custo zero/custo um e capacidades de zero a dois. Incluem também 200 casos com semente fixa, até seis itens e quatro destinos. Um oráculo separado enumera todas as escolhas viáveis e outro verificador exclusivo dos testes confere a matemática residual. Testes adversariais adulteram capacidades, associações, cortes, totais e potenciais, incluindo custo reduzido negativo em componente inalcançável.

## Limitações

Esta é uma biblioteca em desenvolvimento, não um pacote geral de pesquisa operacional ou escalonamento. Cada item exige exatamente uma vaga; destinos possuem capacidades independentes. Não há janelas de tempo, demandas de múltiplas vagas, garantia de equidade ou alegação de segurança operacional. Custos de preferência não representam moeda. Pagamentos, decisões financeiras, decisões clínicas e decisões de pessoal estão fora do escopo. O trabalho é limitado por orçamento, sem promessa de latência ou desempenho. O verificador compartilha a validação de entrada, mas não a construção do grafo ou a otimização; os testes independentes reduzem, sem eliminar, o risco de erros.

Contato geral: [site oficial](https://lofranotecnologia.com.br). Relatos de segurança: [relato privado de vulnerabilidade no GitHub](https://github.com/martinlofranodeoliveira/capability-match-core/security/advisories/new). Consulte [SECURITY.md](SECURITY.md).
