<?php
require_once '../config.php';
header('Content-Type: application/json; charset=utf-8');
$userId=requireAuth(); $input=json_decode(file_get_contents('php://input'),true)?:[];
$title=trim($input['title']??''); $content=trim($input['content']??''); $question=trim($input['question']??'');
if($question===''){http_response_code(400);echo json_encode(['success'=>false,'message'=>'Escreve a tua dúvida.']);exit;}
$content=mb_substr(strip_tags($content),0,14000); $question=mb_substr($question,0,1500);
$prompt="Tu és o Idefy a acompanhar uma pessoa que está a executar um plano.\nIDEIA: $title\nPLANO: $content\nDÚVIDA: $question\nResponde em português de Portugal, de forma concreta e encorajadora. Explica a resposta em passos curtos e, se houver uma suposição, deixa-a explícita. Não inventes dados externos nem repitas o plano inteiro.";
$response=callGeminiAPI($prompt);$answer=trim($response['choices'][0]['message']['content']??'');
if($answer===''){http_response_code(502);echo json_encode(['success'=>false,'message'=>'Não foi possível responder agora.']);exit;}
echo json_encode(['success'=>true,'answer'=>nl2br(htmlspecialchars($answer,ENT_QUOTES,'UTF-8'))],JSON_UNESCAPED_UNICODE);
?>