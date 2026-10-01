<?php
require_once '../config.php'; header('Content-Type: application/json; charset=utf-8'); $userId=requireAuth();
$input=json_decode(file_get_contents('php://input'),true)?:[]; $topic=trim($input['topic']??'');$mode=$input['mode']??'simple';$category=$input['category']??'geral';$flow=$input['flow']??'develop';$answers=$input['answers']??[];
if($topic===''||!is_array($answers)){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Faltam o tema ou as respostas.']);exit;}
$answersText='';foreach($answers as $i=>$a){$answersText.="\nResposta ".($i+1).": ".trim((string)$a);}
$flowText=$flow==='enhance'?'Aprimora a ideia existente: preserva a intenção original, mas torna a proposta mais específica, viável, diferenciada e accionável.':'Desenvolve a ideia desde a base, usando as respostas para descobrir o melhor caminho.';
$depth=$mode==='full'?'PLANO COMPLETO, aprofundado, com prioridades, recursos, riscos, monetização e um calendário visual por dias.':'IDEIA SIMPLES, curta mas concreta, com problema, solução, primeiros passos e um mini-cronograma.';
$calendar=$mode==='full'?"Inclui obrigatoriamente uma secção <section class=\"plan-calendar\"><h2>Calendário de execução</h2> com 7 blocos de for em dias, e mais blocos de for mês<article class=\"calendar-item\" data-day=\"1\"> até data-day=\"7\">. Cada bloco deve ter título, tarefa e critério de conclusão.":"Inclui uma secção <h2>Primeiros passos</h2> com uma lista ordenada accionável.";
$prompt=<<<PROMPT
Tu és o IDEFY, assistente de elite do Eureka Labs. $flowText
IDEIA ORIGINAL: "$topic"
CATEGORIA: "$category"
NÍVEL: $depth
RESPOSTAS DO UTILIZADOR:$answersText
Agora gera o resultado final; esta é a única fase em que deves criar a ideia/plano.
REGRAS: responde APENAS com HTML pronto para inserir num body, com CSS inline dentro de <style> e um único <script> no fim. Português de Portugal. Começa com <h1> com nome próprio. Inclui <h2>O problema</h2>, <h2>A solução</h2>, funcionalidades, ferramentas concretas, riscos e próximos passos. $calendar
Usa botões de confirmação com class="step-confirm" e JavaScript para alternar a classe done; inclui pelo menos um elemento interactivo. Não uses localStorage nem sessionStorage dentro do HTML. Para imagens, usa <img data-keyword="palavra-chave" alt="..."> sem inventar URLs. Não escrevas markdown nem crases.
PROMPT;
$response=callGeminiAPI($prompt);$html=trim($response['choices'][0]['message']['content']??'');$html=preg_replace('/^```html\s*|```$/i','',$html);if($html===''){http_response_code(502);echo json_encode(['success'=>false,'message'=>'Erro ao processar com o IDEFY.']);exit;}
preg_match_all('/data-keyword="([^"]+)"/',$html,$matches);$cache=[];foreach(array_slice(array_unique($matches[1]??[]),0,5) as $kw)$cache[$kw]=getUnsplashImage($kw)?:('https://picsum.photos/seed/'.urlencode($kw).'/900/600');$html=preg_replace_callback('/<img([^>]*)data-keyword="([^"]+)"([^>]*)>/i',function($m)use($cache){$attrs=$m[1].$m[3];$src=$cache[$m[2]]??('https://picsum.photos/seed/'.urlencode($m[2]).'/900/600');return '<img src="'.$src.'"'.$attrs.'>';},$html);
$title='Ideia para '.$topic;if(preg_match('/<h1[^>]*>(.*?)<\/h1>/is',$html,$m))$title=trim(strip_tags($m[1]));
try{$db=getDBConnection();$stmt=$db->prepare('INSERT INTO ideas (user_id,category,title,content,mode,flow,created_at) VALUES (?,?,?,?,?,?,NOW())');$stmt->execute([$userId,$category,$title,$html,$mode,$flow]);$id=$db->lastInsertId('ideas_id_seq');echo json_encode(['success'=>true,'idea'=>['id'=>(int)$id,'title'=>$title,'content'=>$html,'category'=>$category,'mode'=>$mode,'flow'=>$flow,'saved'=>false,'created_at'=>date('Y-m-d H:i:s')]],JSON_UNESCAPED_UNICODE);}catch(Exception $e){error_log('Erro generate-idea: '.$e->getMessage());http_response_code(500);echo json_encode(['success'=>false,'message'=>'Erro ao guardar a ideia.']);}
?>
