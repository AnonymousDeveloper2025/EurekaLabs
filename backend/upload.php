<?php
header('Content-Type: text/html; charset=utf-8');

$repoOwner = "AnonymousDeveloper2025";
$repoName = "AngoNet-page";
$branch = "main";

$token = getenv('GitHub_token');

if (!$token) {
    die("<b>Erro:</b> Variável GitHub_token não encontrada no Environment do Render. Vai em Dashboard > Environment e adiciona.");
}

// Função para enviar ficheiro para GitHub
function githubUpload($fileName, $fileData, $message, $token, $repoOwner, $repoName, $branch, $sha = null) {
    $fileName = rawurlencode($fileName);
    $url = "https://api.github.com/repos/$repoOwner/$repoName/contents/$fileName";

    $content = base64_encode($fileData);
    $data = [
        "message" => $message,
        "content" => $content,
        "branch" => $branch
    ];

    if ($sha) {
        $data["sha"] = $sha;
    }

    $options = [
        "http" => [
            "header" => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-GitHub-Upload\r\n",
            "method" => "PUT",
            "content" => json_encode($data),
            "ignore_errors" => true
        ]
    ];

    $context = stream_context_create($options);
    $result = @file_get_contents($url, false, $context);

    $http_code = $http_response_header[0] ?? "HTTP/1.1 000 Unknown";
    return [$result, $http_code];
}

$uploadOk =
    ($_SERVER['REQUEST_METHOD'] ?? '') === 'POST' &&
    isset($_FILES['file'], $_FILES['image']) &&
    is_array($_FILES['file']) &&
    is_array($_FILES['image']) &&
    $_FILES['file']['error'] === UPLOAD_ERR_OK &&
    $_FILES['image']['error'] === UPLOAD_ERR_OK;

if ($uploadOk) {
    $nome = htmlspecialchars($_POST['nome'] ?? 'Sem nome', ENT_QUOTES, 'UTF-8');
    $descricao = htmlspecialchars($_POST['descricao'] ?? 'Sem descrição', ENT_QUOTES, 'UTF-8');

    // 1. Ficheiro principal
    $fileName = $_FILES['file']['name'];
    $tmpFile = $_FILES['file']['tmp_name'];

    if (!is_uploaded_file($tmpFile)) {
        die("<b>Erro:</b> Ficheiro principal inválido ou não enviado corretamente.");
    }

    $fileData = file_get_contents($tmpFile);

    list($resFile, $codeFile) = githubUpload(
        $fileName,
        $fileData,
        "Upload ficheiro via PHP",
        $token,
        $repoOwner,
        $repoName,
        $branch
    );

    if (strpos($codeFile, "201") === false && strpos($codeFile, "200") === false) {
        die("<b>Erro ao enviar ficheiro:</b> $codeFile <br><pre>$resFile</pre>");
    }

    $jsonFile = json_decode($resFile, true);

    // 2. Imagem
    $imageName = $_FILES['image']['name'];
    $tmpImage = $_FILES['image']['tmp_name'];

    if (!is_uploaded_file($tmpImage)) {
        die("<b>Erro:</b> Imagem inválida ou não enviada corretamente.");
    }

    $imageData = file_get_contents($tmpImage);

    list($resImg, $codeImg) = githubUpload(
        $imageName,
        $imageData,
        "Upload imagem via PHP",
        $token,
        $repoOwner,
        $repoName,
        $branch
    );

    if (strpos($codeImg, "201") === false && strpos($codeImg, "200") === false) {
        die("<b>Erro ao enviar imagem:</b> $codeImg <br><pre>$resImg</pre>");
    }

    $jsonImg = json_decode($resImg, true);

    // 3. Atualizar metadados dados.json
    $metaFile = "dados.json";
    $urlMeta = "https://api.github.com/repos/$repoOwner/$repoName/contents/$metaFile";

    $metaData = [];
    $shaMeta = null;

    $optionsGet = [
        "http" => [
            "header" => "Authorization: token $token\r\nUser-Agent: PHP-GitHub-Upload\r\n",
            "ignore_errors" => true
        ]
    ];

    $metaResponse = @file_get_contents($urlMeta, false, stream_context_create($optionsGet));

    if ($metaResponse) {
        $metaJson = json_decode($metaResponse, true);

        if (isset($metaJson['content'])) {
            $decoded = base64_decode($metaJson['content'], true);
            $metaData = json_decode($decoded, true) ?? [];
            $shaMeta = $metaJson['sha'] ?? null;
        }
    }

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
        "branch" => $branch
    ];

    if ($shaMeta) {
        $dataMeta["sha"] = $shaMeta;
    }

    $optionsMeta = [
        "http" => [
            "header" => "Authorization: token $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-GitHub-Upload\r\n",
            "method" => "PUT",
            "content" => json_encode($dataMeta),
            "ignore_errors" => true
        ]
    ];

    $contextMeta = stream_context_create($optionsMeta);
    $resMeta = @file_get_contents($urlMeta, false, $contextMeta);
    $codeMeta = $http_response_header[0] ?? "";

    if (strpos($codeMeta, "201") === false && strpos($codeMeta, "200") === false) {
        die("<b>Erro ao atualizar dados.json:</b> $codeMeta <br><pre>$resMeta</pre>");
    }

    echo "<h3>✅ Upload concluído!</h3>";
    echo "Ficheiro: <a href='$fileLink' target='_blank'>$fileName</a><br>";
    echo "Imagem: <a href='$imgLink' target='_blank'>$imageName</a><br>";
    echo "Metadados atualizados em dados.json";

} else {
    echo "<pre>";
    echo "REQUEST_METHOD: " . htmlspecialchars($_SERVER['REQUEST_METHOD'] ?? 'não definido') . PHP_EOL . PHP_EOL;
    echo "_FILES:" . PHP_EOL;
    print_r($_FILES);
    echo "</pre>";
    echo "<br><b>Erro no upload.</b> Verifica se selecionaste os 2 ficheiros e se o form está com enctype='multipart/form-data'";
}
?>
