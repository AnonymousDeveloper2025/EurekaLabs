<?php
header('Content-Type: text/html; charset=utf-8');

// CONFIGURAÇÕES
$repoOwner = "AnonymousDeveloper2025"; // <-- teu username GitHub
$repoName  = "AngoNet-page";           // <-- nome do repositório EXATO
$branch    = "main";                   // <-- main ou master
$token     = "ghp_coohfq949QLSefx7B"."WKpjGZZO2whMt3wFthG";  // <-- teu PAT com scope "repo"

// Função para enviar ficheiro para GitHub com retorno de erro
function githubUpload($fileName, $fileData, $message, $token, $repoOwner, $repoName, $branch, $sha = null) {
    $fileName = rawurlencode($fileName); // evita problema com espaço e acento
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
            "header"  => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-GitHub-Upload\r\n",
            "method"  => "PUT",
            "content" => json_encode($data),
            "ignore_errors" => true // pra capturar erro 400/401/404
        ]
    ];
    $context  = stream_context_create($options);
    $result = file_get_contents($url, false, $context);
    
    $http_code = $http_response_header[0] ?? "HTTP/1.1 000 Unknown";
    return [$result, $http_code];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $_FILES['file']['error'] === UPLOAD_ERR_OK && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
    $nome      = htmlspecialchars($_POST['nome'] ?? 'Sem nome');
    $descricao = htmlspecialchars($_POST['descricao'] ?? 'Sem descrição');

    // 1. Ficheiro principal
    $fileName  = $_FILES['file']['name'];
    $fileData  = file_get_contents($_FILES['file']['tmp_name']);
    list($resFile, $codeFile) = githubUpload($fileName, $fileData, "Upload ficheiro via PHP", $token, $repoOwner, $repoName, $branch);
    
    if(strpos($codeFile, "201") === false && strpos($codeFile, "200") === false){
        die("<b>Erro ao enviar ficheiro:</b> $codeFile <br><pre>$resFile</pre>");
    }
    $jsonFile = json_decode($resFile, true);

    // 2. Imagem
    $imageName = $_FILES['image']['name'];
    $imageData = file_get_contents($_FILES['image']['tmp_name']);
    list($resImg, $codeImg) = githubUpload($imageName, $imageData, "Upload imagem via PHP", $token, $repoOwner, $repoName, $branch);

    if(strpos($codeImg, "201") === false && strpos($codeImg, "200") === false){
        die("<b>Erro ao enviar imagem:</b> $codeImg <br><pre>$resImg</pre>");
    }
    $jsonImg = json_decode($resImg, true);

    // 3. Atualizar metadados dados.json
    $metaFile = "dados.json";
    $urlMeta  = "https://api.github.com/repos/$repoOwner/$repoName/contents/$metaFile";

    $metaData = [];
    $shaMeta = null;

    $optionsGet = [
        "http" => ["header" => "Authorization: token $token\r\nUser-Agent: PHP-GitHub-Upload\r\n", "ignore_errors" => true]
    ];
    $metaResponse = @file_get_contents($urlMeta, false, stream_context_create($optionsGet));
    
    if ($metaResponse) {
        $metaJson = json_decode($metaResponse, true);
        if(isset($metaJson['content'])){
            $decoded  = base64_decode($metaJson['content']);
            $metaData = json_decode($decoded, true) ?? [];
            $shaMeta = $metaJson['sha'];
        }
    }

    // Usa o link "download_url" que o GitHub retorna, é mais seguro
    $fileLink = $jsonFile['content']['download_url'] ?? "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/$fileName";
    $imgLink = $jsonImg['content']['download_url'] ?? "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/$imageName";

    $metaData[$fileName] = [
        "nome" => $nome,
        "descricao" => $descricao,
        "ficheiro" => $fileName,
        "imagem" => $imageName,
        "link" => $fileLink,
        "imagem_link" => $imgLink,
        "downloads" => 0,
        "data_upload" => date('Y-m-d H:i:s')
    ];

    $metaContent = base64_encode(json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
    $dataMeta = [
        "message" => "Atualizar metadados - $fileName",
        "content" => $metaContent,
        "branch"  => $branch
    ];
    if ($shaMeta) $dataMeta["sha"] = $shaMeta;

    $optionsMeta = [
        "http" => [
            "header"  => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-GitHub-Upload\r\n",
            "method"  => "PUT",
            "content" => json_encode($dataMeta),
            "ignore_errors" => true
        ]
    ];
    list($resMeta, $codeMeta) = [file_get_contents($urlMeta, false, stream_context_create($optionsMeta)), $http_response_header[0]];
    
    if(strpos($codeMeta, "201") === false && strpos($codeMeta, "200") === false){
        die("<b>Erro ao atualizar dados.json:</b> $codeMeta <br><pre>$resMeta</pre>");
    }

    echo "<h3>✅ Upload concluído!</h3>";
    echo "Ficheiro: <a href='$fileLink' target='_blank'>$fileName</a><br>";
    echo "Imagem: <a href='$imgLink' target='_blank'>$imageName</a><br>";
    echo "Metadados atualizados em dados.json";

} else {
    echo "<b>Erro no upload.</b> Verifica se selecionaste os 2 ficheiros e se o form está com enctype='multipart/form-data'";
}
?>
