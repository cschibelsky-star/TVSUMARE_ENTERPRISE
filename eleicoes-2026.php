<?php $active='eleicoes'; ?><!doctype html>
<html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Eleições 2026 | TV Sumaré</title><link rel="stylesheet" href="/assets/style.css">
<style>
.election-page{max-width:1120px;margin:32px auto;padding:0 20px;color:#10213b}.election-page h1{font-size:clamp(28px,4vw,42px);margin:12px 0}.election-page p{line-height:1.6}.election-grid{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:20px;margin:28px 0}.race{border:1px solid #d4deeb;border-radius:16px;padding:24px;background:#fff}.race h2{font-size:22px;margin-top:0}.race ul{list-style:none;padding:0}.candidate{padding:16px 0;border-bottom:1px solid #e2e8f0}.candidate-line{display:flex;justify-content:space-between;gap:12px;font-weight:700}.candidate small{display:block;margin:6px 0;color:#475569}.race progress{width:100%;height:10px;accent-color:#174ea6}.race time{display:block;font-size:13px;margin-top:16px;color:#475569}.status{padding:12px;background:#eff4fc;border-radius:8px}.warning{background:#fff3cd;color:#614700}.election-page a{color:#174ea6;text-decoration:underline}.election-page a:focus-visible{outline:3px solid #174ea6;outline-offset:4px}@media(max-width:850px){.election-grid{grid-template-columns:1fr}}
</style></head><body><?php include __DIR__.'/header.php'; ?>
<main class="election-page"><a href="/">← TV Sumaré</a><h1>Eleições 2026 — Apuração oficial</h1>
<p>1º turno • 4 de outubro de 2026. Presidente no Brasil; Governador e Senador em São Paulo.</p>
<p>Fonte: Tribunal Superior Eleitoral. Atualização automática a cada 30 segundos. Resultados parciais podem mudar.</p>
<div id="election-status" role="status">Consultando a fonte oficial…</div><div id="election-grid" class="election-grid"></div>
<p>Os percentuais são os informados pelo TSE. No Senado, cada eleitor pode votar em dois candidatos; o percentual não representa a proporção de eleitores.</p>
<p><a href="https://resultados.tse.jus.br/oficial/app/index.html" target="_blank" rel="noopener noreferrer">Consultar resultados no TSE</a></p>
<noscript>Ative o JavaScript para acompanhar os dados, ou consulte o site oficial do TSE pelo link acima.</noscript>
</main><script>
(()=>{const grid=document.getElementById('election-grid'),status=document.getElementById('election-status');
const number=new Intl.NumberFormat('pt-BR'),percent=new Intl.NumberFormat('pt-BR',{minimumFractionDigits:2,maximumFractionDigits:2});
function el(tag,text,cls){const node=document.createElement(tag);if(text!==undefined)node.textContent=text;if(cls)node.className=cls;return node}
function render(data){const fragment=document.createDocumentFragment();
for(const race of data.races){const card=el('section',undefined,'race');card.append(el('h2',race.title));
if(race.unavailable){card.append(el('p','Aguardando dados oficiais. A fonte ainda não pôde ser consultada.','status warning'));}
else{card.append(el('p',percent.format(race.sections)+'% das seções totalizadas'));const progress=el('progress');progress.max=100;progress.value=race.sections;progress.setAttribute('aria-label','Seções totalizadas');card.append(progress);
if(race.stale)card.append(el('p','Atualização atrasada. Exibindo a última leitura válida.','status warning'));
else card.append(el('p',race.final?'Totalização final informada pelo TSE':'Apuração parcial','status'));
const list=el('ul');for(const candidate of race.candidates){const row=el('li',undefined,'candidate'),line=el('div',undefined,'candidate-line');line.append(el('span',candidate.name),el('span',percent.format(candidate.percent)+'%'));row.append(line,el('small',candidate.party+' • '+candidate.number+' • '+number.format(candidate.votes)+' votos'));if(candidate.status)row.append(el('small','Situação no TSE: '+candidate.status));list.append(row)}card.append(list);
const time=el('time','Arquivo do TSE: '+new Date(race.updated_at).toLocaleString('pt-BR',{timeZone:'America/Sao_Paulo'})+' (Brasília)');time.dateTime=race.updated_at;card.append(time);}
fragment.append(card)}grid.replaceChildren(fragment);status.textContent='';}
async function refresh(){if(document.hidden){setTimeout(refresh,30000);return}const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),20000);try{const response=await fetch('/eleicoes-2026-dados.php',{signal:controller.signal,cache:'no-store'});if(!response.ok)throw new Error();const data=await response.json();if(!Array.isArray(data.races))throw new Error();render(data);}catch(error){status.textContent='Não foi possível atualizar agora. Nova tentativa em 30 segundos; confira o horário dos dados abaixo.';}finally{clearTimeout(timer);setTimeout(refresh,30000)}}refresh();})();
</script></body></html>
