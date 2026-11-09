# Contrato de diagnóstico HeyGen Digital Twin — Issue #63

## Objetivo
Comprovar, sem gerar vídeos, se a credencial do runtime da TV Sumaré pode consultar o look Digital Twin `c68420874a454f3bbf7200a48add0288`.

## Pré-condições
- Usar a credencial já provisionada no runtime; nunca imprimir ou versionar seu valor.
- Executar somente em ambiente autorizado, por CLI; nenhuma chamada de geração.
- Não alterar o apresentador, avatar, voz ou configuração de produção.
- O ID do look foi identificado no catálogo de uma conta HeyGen conectada ao ChatGPT; isso **não prova** acesso pela credencial da TV Sumaré.

## Contrato de saída
- `key_introspection.ok`: sucesso HTTP e JSON válido de GET /v3/api_keys/self.
- `key_introspection.http`: código HTTP, sem corpo da resposta.
- `key_introspection.scope_mode`: campo opcional; não é autorização inferida.
- `key_introspection.can_read_avatars`: indicador informativo, não comprovação de permissão.
- `avatar_check.ok`: catálogo lido e interpretado sem paginação pendente.
- `avatar_check.avatar_visible`: ID encontrado no conjunto inspecionado; só é conclusivo quando o catálogo é completo.
- Falha, paginação, formato desconhecido e ausência do ID **não autorizam** afirmar que o look está indisponível.

## Critérios de aceite / gates
1. CI: lint PHP, testes mock de sucesso/erro/paginação e falha segura sem credencial.
2. Runtime: validar extensão cURL, política de saída, carregamento seguro da chave e acesso HTTPS sem revelar secrets.
3. Evidência autenticada: GET /v3/api_keys/self e GET /v3/avatars/looks usando **a mesma identidade do serviço**.
4. Confirmar formato da resposta e paginação; identificar look e permissões de maneira inequívoca.
5. Teste de integração HML do Repórter IA, sem gerar vídeo pago, até autorização específica.
6. Supervisão VIA: `can_advance=false` até evidências externas verificadas e aprovação humana quando necessária.

## Riscos conhecidos
- O script CLI atual usa getenv('HEYGEN_API_KEY'), mas o runtime pode manter a chave em configuração não exportada para o processo CLI.
- O helper presume formatos de catálogo e marcadores de paginação que ainda precisam ser validados com a API real.
- O diagnóstico não usa automaticamente a política de saída do cliente HeyGen existente no Repórter IA.
- Não transformar um CI verde em autorização para merge, deploy ou cobrança.

## Escopo da implementação
Branch `feat/issue-63-digital-twin-readonly-check`; PR #64. Sem merge, deploy, migration, alteração de produção ou consumo de créditos.
