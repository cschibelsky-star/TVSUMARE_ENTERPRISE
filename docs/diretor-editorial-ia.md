# Diretor Editorial IA — TV Sumaré

Regra canônica: matérias cujo município principal confirmado é Sumaré/SP usam Cristian Schibelsky (avatar `ecc586fbd3e94cd4a07347c098364fc8`, voz privada `ca1ad88a041a40eea2f1c8583f970ce4`). As demais cidades e notícias nacionais/internacionais usam apresentador virtual sem nome, avatar/voz globais ou crédito pessoal. GC externo mostra somente título, cidade e editoria. Localidade ausente, regional ou multicípio exige revisão.

A verificação é determinística, aplicada imediatamente antes de cada envio ao HeyGen, e não somente um prompt. Os módulos Repórter IA, Boletim IA e assistente de vídeos compartilham `includes/editorial_director.php`. Cada job corresponde a uma pauta; boletins de cidades diferentes são jobs separados. A aprovação editorial humana continua obrigatória.

Os IDs de Cristian acima são defaults da política; podem ser substituídos por `heygen_cristian_avatar_id`/`heygen_cristian_voice_id` na configuração ou pelas variáveis `HEYGEN_CRISTIAN_AVATAR_ID`/`HEYGEN_CRISTIAN_VOICE_ID`. IDs globais legados `heygen_avatar_id` e `heygen_voice_id` não substituem a identidade oficial nem são enviados para outras cidades. O BrandKit padrão é TV SUMARÉ — OFICIAL (`1a38baabf787404590ba2b36f922248d`); é possível sobrescrevê-lo com `heygen_editorial_brand_kit_id`, `HEYGEN_EDITORIAL_BRAND_KIT_ID` ou a configuração geral do BrandKit. Disponibilidade e permissão da credencial operacional para esse kit ainda dependem de validação em HML.

Campos de matéria enviados a modelos são delimitados como dados não confiáveis; instruções embutidas neles não podem alterar a política editorial. O envio de roteiro externo que contenha assinatura de Cristian é bloqueado para revisão. O Video Agent v3 permite identidade virtual sem IDs explícitos. O fluxo de TV Play mantém o endpoint Video Agent v3; não há geração de vídeo executada nesta validação.

Validações locais previstas: `php tests/editorial_director_test.php` e lint PHP completo; o teste do Diretor Editorial também é executado pelo workflow `TV Sumare CI`. A geração controlada e a verificação da credencial/BrandKit em HML não foram executadas. A compatibilidade de payload com a migração de endpoint do PR #25 e a branch efetiva da VPS precisam ser confirmadas no ambiente/branch aplicável; a VPS não foi acessada nem alterada.

Este PR permanece isolado, sem merge, deploy, migrations, alteração de secrets ou chamadas pagas de geração. Aprovação editorial e publicação manual permanecem obrigatórias.
