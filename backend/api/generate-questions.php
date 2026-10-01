<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
$userId = requireAuth();
$input = json_decode(file_get_contents('php://input'), true) ?: [];
$topic = trim($input['topic'] ?? ''); $category = trim($input['category'] ?? 'geral'); $mode = $input['mode'] ?? 'simple'; $flow = $input['flow'] ?? 'develop';
if ($topic === '') { http_response_code(400); echo json_encode(['success'=>false,'message'=>'Indica primeiro o tema ou a ideia.']); exit; }
$flowText = $flow === 'enhance' ? 'A pessoa já tem esta ideia e quer aprimorá-la, tornando-a mais clara, viável e diferenciada.' : 'A pessoa quer desenvolver esta ideia a partir do zero.';
$prompt = <<<PROMPT
Tu és o Idefy. $flowText
Tema/ideia: "$topic"
Categoria: "$category". Profundidade escolhida: "$mode".
Gera 4 a 6 perguntas que realmente mudem a qualidade do resultado final. Pergunta contexto útil: localização, público, recursos/orçamento, proximidades/oportunidades, experiência, prazo e restrições, mas adapta ao tema e não repitas perguntas óbvias.
Responde APENAS JSON válido neste formato: {"questions":[{"question":"...","help":"...","type":"choice","options":["...","...","...","..."]}]}.
Usa type "choice" quando houver opções úteis (3 a 5 opções) e type "text" quando a resposta precisar de contexto livre. Português de Portugal. Não cries a ideia nem o plano ainda.
PROMPT;
$response = callGeminiAPI($prompt);
$text = $response['choices'][0]['message']['content'] ?? '';
$text = trim(preg_replace('/^```(?:json)?\s*|```$/i','',$text));
$data = json_decode($text, true);
if (!$data || !isset($data['questions']) || !is_array($data['questions'])) { http_response_code(502); echo json_encode(['success'=>false,'message'=>'O Idefy não conseguiu preparar perguntas válidas. Tenta novamente.']); exit; }
$questions=[]; foreach(array_slice($data['questions'],0,6) as $q){ if(!empty($q['question'])) $questions[]=['question'=>trim($q['question']),'help'=>trim($q['help']??''),'type'=>($q['type']??'text')==='choice'?'choice':'text','options'=>array_values(array_filter(array_map('strval',$q['options']??[])))]; }
if(count($questions)<2){http_response_code(502);echo json_encode(['success'=>false,'message'=>'O Idefy gerou poucas perguntas. Tenta novamente.']);exit;}
echo json_encode(['success'=>true,'questions'=>$questions], JSON_UNESCAPED_UNICODE);
?>