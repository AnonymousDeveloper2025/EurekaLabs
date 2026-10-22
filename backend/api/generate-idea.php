<?php
require_once '../config.php'; header('Content-Type: application/json; charset=utf-8'); $userId=requireAuth();
$input=json_decode(file_get_contents('php://input'),true)?:[]; $topic=trim($input['topic']??'');$mode=$input['mode']??'simple';$category=$input['category']??'geral';$flow=$input['flow']??'develop';$answers=$input['answers']??[];
if($topic===''||!is_array($answers)){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Faltam o tema ou as respostas.']);exit;}
$answersText='';foreach($answers as $i=>$a){$answersText.="\nResposta ".($i+1).": ".trim((string)$a);}
$flowText=$flow==='enhance'?'Aprimora a ideia existente: preserva a intenção original, mas torna a proposta mais específica, viável, diferenciada e accionável.':'Desenvolve a ideia desde a base, usando as respostas para descobrir o melhor caminho.';
$depth=$mode==='full'?'PLANO COMPLETO, aprofundado, com prioridades, recursos, riscos, monetização e um calendário visual por dias.':'IDEIA SIMPLES, curta mas concreta, com problema, solução, primeiros passos e um mini-cronograma.';
$calendar=$mode==='full'?"Inclui obrigatoriamente uma secção <section class=\"plan-calendar\"><h2>Calendário de execução</h2> com 7 blocos <article class=\"calendar-item\" data-day=\"1\"> até data-day=\"7\">. Cada bloco deve ter título, tarefa, critério de conclusão e um botão de conclusão.":"Inclui uma secção <h2>Primeiros passos</h2> com uma lista ordenada accionável, cada passo com botão de conclusão.";
$businessLike=(bool)preg_match('/negócio|negocio|empreend|venda|comérc|comerc|finance|renda|serviço|servico|agric|turismo|marketing|startup|loja|freelance|invest|mercado|cliente|produto/i', "$topic $category");
$angolaContext=$businessLike?"Como este é um tema de negócio ou semelhante, adapta-o explicitamente a Angola: usa kwanza (Kz) nos valores e orçamentos, considera poder de compra, informalidade, pagamentos móveis e limitações de internet/logística, identifica oportunidades e riscos locais, e cita cidades/províncias angolanas relevantes (por exemplo Luanda, Benguela, Huíla, Huambo, Cabinda, Namibe ou outras apenas quando fizer sentido). Não inventes estatísticas: assinala estimativas e pressupostos.":"Não forces referências a Angola, Kz, cidades ou províncias se o tema não for de negócio ou semelhante; mantém o foco do utilizador.";
$iframeContract="O HTML será inserido num iframe sandbox. O menu hambúrguer deve ser fixo ou estatístico/estático (não desaparecer ao fazer scroll) e funcionar sem bibliotecas externas. Inclui mais elementos HTML úteis e semânticos: cronograma, diagramas simples em HTML/CSS/SVG, tabelas responsivas que não transbordem do iframe, cartões, ícones de ferramentas mencionadas (podes usar emoji ou SVG inline), e botões de links de redireccionamento com target=\"_blank\" e rel=\"noopener\" quando uma plataforma for recomendada.";
$progressContract="Cria uma barra de progresso no topo do conteúdo, com percentagem visível e um botão ‘Ver estatísticas’ que abre um painel/gráfico de estatísticas. Cada passo executável deve ter um botão ‘Marcar como concluído’, usando class=\"step-confirm\" e data-step-id único, ou data-step-id no elemento. Não uses localStorage nem sessionStorage. O script do iframe deve comunicar apenas com a página-pai por window.parent.postMessage. Ao carregar, envia {type:'idefy-progress-ready'}; quando um passo muda, envia {type:'idefy-progress-change', completedSteps:[...], progressPercent:numero, stats:{...}}. A página-pai responde ao evento {type:'idefy-progress-load', progress:{completed_steps:[...], progress_percent:numero, stats:{...}}}. Ao receber esse evento, marca os passos guardados, recalcula a barra e actualiza o gráfico.";
$prompt=<<<PROMPT
Tu és o IDEFY, assistente de elite do Eureka Labs. $flowText
IDEIA ORIGINAL: "$topic"
CATEGORIA: "$category"
NÍVEL: $depth
RESPOSTAS DO UTILIZADOR:$answersText
Agora gera o resultado final; esta é a única fase em que deves criar a ideia/plano.
REGRAS: responde APENAS com HTML pronto para inserir num body, com CSS inline dentro de <style> e um único <script> no fim. Português de Portugal. Começa com <h1> com nome próprio. Inclui <h2>O problema</h2>, <h2>A solução</h2>, funcionalidades, ferramentas concretas, riscos e próximos passos. $calendar
$angolaContext
$iframeContract
$progressContract
Não uses localStorage nem sessionStorage dentro do HTML. Para imagens, usa <img data-keyword="palavra-chave" alt="..."> sem inventar URLs. Não escrevas markdown nem crases.
PROMPT;
$response=callGeminiAPI($prompt);$html=trim($response['choices'][0]['message']['content']??'');$html=preg_replace('/^```html\s*|```$/i','',$html);if($html===''){http_response_code(502);echo json_encode(['success'=>false,'message'=>'Erro ao processar com o IDEFY.']);exit;}
preg_match_all('/data-keyword="([^"]+)"/',$html,$matches);$cache=[];foreach(array_slice(array_unique($matches[1]??[]),0,5) as $kw)$cache[$kw]=getUnsplashImage($kw)?:('https://picsum.photos/seed/'.urlencode($kw).'/900/600');$html=preg_replace_callback('/<img([^>]*)data-keyword="([^"]+)"([^>]*)>/i',function($m)use($cache){$attrs=$m[1].$m[3];$src=$cache[$m[2]]??('https://picsum.photos/seed/'.urlencode($m[2]).'/900/600');return '<img src="'.$src.'"'.$attrs.'>';},$html);
$title='Ideia para '.$topic;if(preg_match('/<h1[^>]*>(.*?)<\/h1>/is',$html,$m))$title=trim(strip_tags($m[1]));
try{$db=getDBConnection();$stmt=$db->prepare('INSERT INTO ideas (user_id,category,title,content,mode,flow,created_at) VALUES (?,?,?,?,?,?,NOW())');$stmt->execute([$userId,$category,$title,$html,$mode,$flow]);$id=$db->lastInsertId('ideas_id_seq');echo json_encode(['success'=>true,'idea'=>['id'=>(int)$id,'title'=>$title,'content'=>$html,'category'=>$category,'mode'=>$mode,'flow'=>$flow,'saved'=>false,'created_at'=>date('Y-m-d H:i:s')]],JSON_UNESCAPED_UNICODE);}catch(Exception $e){error_log('Erro generate-idea: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Erro ao guardar a ideia.']);}
?>
