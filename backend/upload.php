<?php
// CONFIGURAÇÕES
$repoOwner = "AnonymousDeveloper2025"; // <-- teu username GitHub
$repoName  = "AngoNet-page";           // <-- nome do repositório
$branch    = "main";                   // <-- branch alvo
$token     = "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";  // <-- teu token GitHub (PAT)

// Função para enviar ficheiro para GitHub
function githubUpload($fileName, $fileData, $message, $token, $repoOwner, $repoName, $branch, $sha = null) {
    $url = "https://api.github.com/repos/$repoOwner/$repoName/contents/$fileName";
    $content = base64_encode($fileData);
    $data = [
        "message" => $message,
        "content" => $content,
        "branch"  => $branch
    ];
    if ($sha) $data["sha"] = $sha;

    $options = [
        "http" => [
            "header"  => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP\r\n",
            "method"  => "PUT",
            "content" => json_encode($data)
        ]
    ];
    $context  = stream_context_create($options);
    return file_get_contents($url, false, $context);
}

if ($_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $nome      = $_POST['nome'];
    $descricao = $_POST['descricao'];

    // Ficheiro principal
    $fileName  = $_FILES['file']['name'];
    $fileData  = file_get_contents($_FILES['file']['tmp_name']);
    githubUpload($fileName, $fileData, "Upload ficheiro via PHP", $token, $repoOwner, $repoName, $branch);

    // Imagem
    $imageName = $_FILES['image']['name'];
    $imageData = file_get_contents($_FILES['image']['tmp_name']);
    githubUpload($imageName, $imageData, "Upload imagem via PHP", $token, $repoOwner, $repoName, $branch);

    // Atualizar metadados
    $metaFile = "dados.json";
    $urlMeta  = "https://api.github.com/repos/$repoOwner/$repoName/contents/$metaFile";

    $metaData = [];
    $metaResponse = @file_get_contents($urlMeta, false, stream_context_create([
        "http" => ["header" => "Authorization: token $token\r\nUser-Agent: PHP\r\n"]
    ]));
    $metaJson = null;
    if ($metaResponse) {
        $metaJson = json_decode($metaResponse, true);
        $decoded  = base64_decode($metaJson['content']);
        $metaData = json_decode($decoded, true);
    }

    $metaData[$fileName] = [
        "nome" => $nome,
        "descricao" => $descricao,
        "ficheiro" => $fileName,
        "imagem" => $imageName,
        "link" => "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/$fileName",
        "downloads" => 0
    ];

    $metaContent = base64_encode(json_encode($metaData, JSON_PRETTY_PRINT));
    $dataMeta = [
        "message" => "Atualizar metadados",
        "content" => $metaContent,
        "branch"  => $branch
    ];
    if ($metaJson) $dataMeta["sha"] = $metaJson['sha'];

    $optionsMeta = [
        "http" => [
            "header"  => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP\r\n",
            "method"  => "PUT",
            "content" => json_encode($dataMeta)
        ]
    ];
    file_get_contents($urlMeta, false, stream_context_create($optionsMeta));

    echo "Upload concluído!";
} else {
    echo "Erro no upload.";
}
?>
