<?php
header("Content-Type: application/json");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') exit;

$repoOwner = "AnonymousDeveloper2025";
$repoName  = "AngoNet-page";
$branch    = "main";

// Aceita tanto GitHub_token quanto GITHUB_TOKEN
$token = getenv("GitHub_token") ?: getenv("GITHUB_TOKEN");

$fileName = $_POST['file'] ?? null;
if (!$fileName) {
    echo json_encode(["erro" => "Ficheiro não especificado"]);
    exit;
}

$urlMeta  = "https://api.github.com/repos/$repoOwner/$repoName/contents/dados.json";

$response = file_get_contents($urlMeta, false, stream_context_create([
    "http" => ["header" => "Authorization: token $token\r\nUser-Agent: PHP\r\n", "ignore_errors" => true]
]));

if ($response === false) {
    echo json_encode(["erro" => "Não foi possível aceder ao GitHub"]);
    exit;
}

$metaJson = json_decode($response, true);
$decoded  = base64_decode($metaJson['content']);
$metaData = json_decode($decoded, true);

if (isset($metaData[$fileName])) {
    $metaData[$fileName]['downloads'] = ($metaData[$fileName]['downloads'] ?? 0) + 1;
}

$metaContent = base64_encode(json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));

$dataMeta = [
    "message" => "Incrementar downloads - $fileName",
    "content" => $metaContent,
    "branch"  => $branch,
    "sha"     => $metaJson['sha']
];

$optionsMeta = [
    "http" => [
        "header"  => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP\r\n",
        "method"  => "PUT",
        "content" => json_encode($dataMeta),
        "ignore_errors" => true
    ]
];

file_get_contents($urlMeta, false, stream_context_create($optionsMeta));

echo json_encode([
    "link" => $metaData[$fileName]['link'] ?? "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/" . rawurlencode($fileName)
]);
?>