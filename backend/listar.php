<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");

$repoOwner = "AnonymousDeveloper2025";
$repoName  = "AngoNet-page";
$branch    = "main";

$urlMeta = "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/dados.json?t=" . time();

$response = @file_get_contents($urlMeta);

if ($response === false) {
    echo json_encode([]);
    exit;
}

$metaData = json_decode($response, true);
if (!$metaData) { echo json_encode([]); exit; }

$saida = [];
foreach ($metaData as $item) {
    $saida[] = [
        "nome" => $item["nome"],
        "descricao" => $item["descricao"],
        "ficheiro" => $item["ficheiro"],
        "imagem" => $item["imagem_link"] ?? "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/" . $item["imagem"],
        "link" => $item["link"],
        "downloads" => $item["downloads"] ?? 0
    ];
}

echo json_encode($saida, JSON_UNESCAPED_UNICODE);
?>