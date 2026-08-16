<?php
require_once __DIR__.'/auth.php';
require_login();
require_once __DIR__.'/monitor_lib.php';
$activeAdmin='validade';

function cv_h($v){ return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8'); }
function cv_path($name){ return dirname(__DIR__).'/data/'.$name; }
function cv_read($name){ $d=tvs_read_json_file(cv_path($name)); return is_array($d)?$d:[]; }
function cv_write($name,$rows){ return tvs_save_json_file(cv_path($name),array_values($rows)); }
function cv_date($n){ foreach(['published_at','created_at','date'] as $k){ if(!empty($n[$k])){ $ts=strtotime((string)$n[$k]); if($ts) return $ts; } } return 0; }
function cv_date_label($n){ $ts=cv_date($n); return $ts?date('d/m/Y H:i',$ts):'sem data'; }
function cv_sensitive($n){ $t=tvs_lower(tvs_clean_text(($n['category']??'').' '.($n['title']??'').' '.($n['subtitle']??'').' '.($n['summary']??''))); return preg_match('~\b(vagas?|empregos?|processo seletivo|concurso|inscri[cç][oõ]es|edital|evento|agenda|programa[cç][aã]o|interdi[cç][aã]o|tr[aâ]nsito|vacina[cç][aã]o|campanha|prazo|atendimento|curso|matr[ií]cula|feira|show|festival)\b~iu',$t)===1; }
function cv_limit($n){ return cv_sensitive($n)?7:30; }
function cv_age($n){ $ts=cv_date($n); return $ts?max(0,(int)floor((time()-$ts)/86400)):null; }
function cv_needs_review($n){ $age=cv_age($n); $limit=cv_limit($n); $checked=strtotime((string)($n['validity_checked_at']??'')); if($checked && (time()-$checked)<($limit*86400)) return false; if(($n['validity_status']??'')==='revisao_solicitada') return true; if($age===null) return true; return $age>=$limit; }
function cv_reason($n){ $age=cv_age($n); if($age===null) return 'Data editorial não identificada.'; if(cv_sensitive($n)) return "Conteúdo temporal/serviço com {$age} dia(s): confirmar prazo, agenda ou validade."; return "Matéria publicada há {$age} dia(s): confirmar se continua atual."; }
function cv_sync_alerts($news){
  $alerts=[];
  foreach($news as $n){
    if(!cv_needs_review($n)) continue;
    $alerts[]=[
      'id'=>'alert_'.substr(hash('sha256',(string)($n['id']??'').($n['title']??'')),0,16),
      'news_id'=>$n['id']??'',
      'title'=>$n['title']??'Sem título',
      'city'=>$n['city']??'Região',
      'category'=>$n['category']??'Notícia',
      'age_days'=>cv_age($n),
      'priority'=>cv_sensitive($n)?'alta':'normal',
      'reason'=>cv_reason($n),
      'status'=>'aguardando_revisao',
      'notification_channels'=>['painel','email_quando_configurado','whatsapp_quando_configurado'],
      'detected_at'=>date('c')
    ];
  }
  cv_write('content_validity_alerts.json',$alerts);
  return $alerts;
}

$news=cv_read('noticias.json');
$log=cv_read('content_validity_log.json');
$notice='';
$error='';

if(($_SERVER['REQUEST_METHOD']??'GET')==='POST'){
  tvs_verify_csrf();
  $id=(string)($_POST['id']??'');
  $action=(string)($_POST['action']??'');
  $idx=null;
  foreach($news as $k=>$n){ if((string)($n['id']??'')===$id){ $idx=$k; break; } }

  if($idx===null){
    $error='Matéria não encontrada.';
  } else {
    $now=date('c');
    $title=$news[$idx]['title']??'Sem título';

    if($action==='confirm'){
      $news[$idx]['validity_checked_at']=$now;
      $news[$idx]['validity_status']='confirmada';
      $log[]=['id'=>uniqid('valid_'),'news_id'=>$id,'title'=>$title,'action'=>'VALIDADE_CONFIRMADA','reason'=>'Editor confirmou que o conteúdo continua válido.','created_at'=>$now];
      $notice='Validade confirmada e registrada.';
    } elseif($action==='review'){
      $news[$idx]['validity_status']='revisao_solicitada';
      $news[$idx]['validity_review_requested_at']=$now;
      $log[]=['id'=>uniqid('valid_'),'news_id'=>$id,'title'=>$title,'action'=>'REVISAO_SOLICITADA','reason'=>cv_reason($news[$idx]),'created_at'=>$now];
      $notice='Revisão editorial solicitada.';
    } elseif($action==='archive'){
      $trash=cv_read('lixeira_noticias.json');
      $item=$news[$idx];
      $item['deleted_at']=$now;
      $item['archive_reason']='Conteúdo perdeu validade após revisão editorial.';
      $trash[]=$item;
      array_splice($news,$idx,1);
      cv_write('lixeira_noticias.json',$trash);
      $log[]=['id'=>uniqid('valid_'),'news_id'=>$id,'title'=>$title,'action'=>'ARQUIVADA','reason'=>'Arquivada manualmente após conferência de validade.','created_at'=>$now];
      $notice='Matéria arquivada com rastreabilidade.';
    }

    cv_write('noticias.json',$news);
    $log=array_slice($log,-500);
    cv_write('content_validity_log.json',$log);
    cv_sync_alerts($news);
  }
}

$review=array_values(array_filter($news,'cv_needs_review'));
usort($review,function($a,$b){ return (cv_age($b)??9999)<=>(cv_age($a)??9999); });
?>
<!doctype html>
<html lang="pt-BR">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>Validade Editorial | TV Sumaré</title>
<link rel="stylesheet" href="admin.css?v=2.0.4">
</head>
<body>
<div class="admin">
<?php include __DIR__.'/_menu.php'; ?>
<main class="main">
  <div class="top">
    <div>
      <span class="eyebrow">Governança Editorial</span>
      <h1>Validade das Matérias</h1>
      <p class="muted">Conteúdo temporal entra em revisão após 7 dias; demais matérias após 30 dias. A idade é calculada pela data de publicação exibida na tabela. Nada é removido automaticamente.</p>
    </div>
  </div>

  <?php if($notice): ?><div class="notice"><?=cv_h($notice)?></div><?php endif; ?>
  <?php if($error): ?><div class="notice error"><?=cv_h($error)?></div><?php endif; ?>

  <div class="admin-kpi-grid">
    <div class="admin-kpi"><span>Publicadas</span><strong><?=count($news)?></strong></div>
    <div class="admin-kpi"><span>Pedem checagem</span><strong><?=count($review)?></strong></div>
    <div class="admin-kpi"><span>Registros de validade</span><strong><?=count($log)?></strong></div>
  </div>

  <section class="box">
    <h2>Fila de checagem</h2>
    <?php if(!$review): ?>
      <p class="muted">Nenhuma matéria exige checagem neste momento.</p>
    <?php else: ?>
      <div class="table-wrap">
        <table>
          <thead>
            <tr>
              <th>Matéria</th>
              <th>Publicado em</th>
              <th>Idade</th>
              <th>Regra</th>
              <th>Motivo</th>
              <th>Ação editorial</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach($review as $n): ?>
            <tr>
              <td><strong><?=cv_h($n['title']??'Sem título')?></strong><br><small><?=cv_h(($n['city']??'Região').' • '.($n['category']??'Notícia'))?></small></td>
              <td><?=cv_h(cv_date_label($n))?></td>
              <td><?=cv_h(cv_age($n)===null?'sem data':cv_age($n).' dias')?></td>
              <td><?=cv_h(cv_limit($n).' dias'.(cv_sensitive($n)?' • temporal/serviço':' • geral'))?></td>
              <td><?=cv_h(cv_reason($n))?></td>
              <td>
                <div class="actions">
                  <form method="post"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=cv_h($n['id']??'')?>"><button class="btn secondary" name="action" value="confirm">Confirmar atual</button></form>
                  <form method="post"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=cv_h($n['id']??'')?>"><button class="btn" name="action" value="review">Solicitar revisão</button></form>
                  <form method="post" onsubmit="return confirm('Arquivar esta matéria por perda de validade? Ela irá para a Lixeira e poderá ser restaurada.');"><?=tvs_csrf_field()?><input type="hidden" name="id" value="<?=cv_h($n['id']??'')?>"><button class="btn danger" name="action" value="archive">Arquivar</button></form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div>
    <?php endif; ?>
  </section>

  <section class="box" style="margin-top:16px">
    <h2>Notificação ao Editor-Chefe</h2>
    <p class="muted">A fila acima é o alerta operacional interno. O verificador automático gera <code>data/content_validity_alerts.json</code> para integração com e-mail e, quando disponível/configurado, WhatsApp. A integração externa não remove nem arquiva conteúdo automaticamente.</p>
  </section>
</main>
</div>
</body>
</html>
