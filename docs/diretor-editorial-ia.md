# Diretor Editorial IA — TV Sumaré

Regra canônica: matérias cujo município principal confirmado é Sumaré/SP usam Cristian Schibelsky (avatar e voz oficiais). As demais cidades usam avatar virtual sem identificação nominal. Localidade ausente, regional ou multicípio exige revisão.

A verificação é determinística, aplicada imediatamente antes de cada envio ao HeyGen, e não somente um prompt. Os módulos Repórter IA, Boletim IA e assistente de vídeos compartilham includes/editorial_director.php.

Configurar na HML os IDs CONFIRMADOS de Cristian usando heygen_cristian_avatar_id e heygen_cristian_voice_id. Os IDs genéricos antigos NÃO são presumidos como sendo de Cristian. O kit padrão é TV SUMARÉ — OFICIAL (HeyGen 1a38baabf787404590ba2b36f922248d). Confirmar que a chave operacional tem acesso a ele.

Testar php tests/editorial_director_test.php, PHP lint e dois vídeos controlados (Sumaré/Campinas), validando identidade, GC, voice, logo, vinhetas e tarjas. A presença de imagens na biblioteca HeyGen não garante uso automático, o que exige template de cenas validado.

O PR é isolado, sem merge, sem deploy e sem geração paga. Revisar contra PR #25 (HeyGen v3) e branch efetiva da VPS antes de homologar. Aprovação editorial e publicação manual permanecem obrigatórias.
