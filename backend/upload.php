<?php
// CONFIGURAÇÕES
$repoOwner = "AnonymousDeveloper2025"; // User/Org do GitHub
$repoName  = "AngoNet-page";           // Nome do repositório
$branch    = "main";                   // Branch alvo
$token     = "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";  // Teu Personal Access Token (PAT)

// Função para enviar ou atualizar ficheiro no GitHub
function githubUpload($fileName, $fileData, $message, $token, $repoOwner, $repoName, $branch) {
    $url = "https://api.github.com/repos/$repoOwner/$repoName/contents/$fileName";
    
    // 1. Verificar se o ficheiro já existe no repositório para obter o SHA
    $sha = null;
    $getOptions = [
        "http" => [
            "header" => "Authorization: Bearer $token\r\nUser-Agent: PHP-Script\r\n",
            "method" => "GET",
            "ignore_errors" => true
        ]
    ];
    $getResponse = @file_get_contents($url, false, stream_context_create($getOptions));
    if ($getResponse) {
        $getData = json_decode($getResponse, true);
        if (isset($getData['sha'])) {
            $sha = $getData['sha'];
        }
    }

    // 2. Preparar os dados para o envio (PUT)
    $content = base64_encode($fileData);
    $data = [
        "message" => $message,
        "content" => $content,
        "branch"  => $branch
    ];
    if ($sha) {
        $data["sha"] = $sha;
    }

    $options = [
        "http" => [
            "header"  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-Script\r\n",
            "method"  => "PUT",
            "content" => json_encode($data),
            "ignore_errors" => true
        ]
    ];
    
    return file_get_contents($url, false, stream_context_create($options));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK &&
        isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {

        $nome      = $_POST['nome'] ?? '';
        $descricao = $_POST['descricao'] ?? '';

        // 1. Upload do Ficheiro principal
        $fileName  = $_FILES['file']['name'];
        $fileData  = file_get_contents($_FILES['file']['tmp_name']);
        githubUpload($fileName, $fileData, "Upload ficheiro via PHP", $token, $repoOwner, $repoName, $branch);

        // 2. Upload da Imagem
        $imageName = $_FILES['image']['name'];
        $imageData = file_get_contents($_FILES['image']['tmp_name']);
        githubUpload($imageName, $imageData, "Upload imagem via PHP", $token, $repoOwner, $repoName, $branch);

        // 3. Atualizar ficheiro de metadados (dados.json)
        $metaFile = "dados.json";
        $urlMeta  = "https://api.github.com/repos/$repoOwner/$repoName/contents/$metaFile";

        // Ler dados.json existente
        $metaData = [];
        $metaResponse = @file_get_contents($urlMeta, false, stream_context_create([
            "http" => [
                "header" => "Authorization: Bearer $token\r\nUser-Agent: PHP-Script\r\n",
                "ignore_errors" => true
            ]
        ]));

        $metaJson = null;
        if ($metaResponse) {
            $metaJson = json_decode($metaResponse, true);
            if (isset($metaJson['content'])) {
                $decoded  = base64_decode($metaJson['content']);
                $metaData = json_decode($decoded, true) ?? [];
            }
        }

        // Adicionar o novo registo
        $metaData[$fileName] = [
            "nome"      => $nome,
            "descricao" => $descricao,
            "ficheiro"  => $fileName,
            "imagem"    => $imageName,
            "link"      => "https://raw.githubusercontent.com/$repoOwner/$repoName/$branch/$fileName",
            "downloads" => 0
        ];

        // Guardar as alterações no dados.json no GitHub
        $metaContent = base64_encode(json_encode($metaData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $dataMeta = [
            "message" => "Atualizar metadados",
            "content" => $metaContent,
            "branch"  => $branch
        ];
        if (isset($metaJson['sha'])) {
            $dataMeta["sha"] = $metaJson['sha'];
        }

        $optionsMeta = [
            "http" => [
                "header"  => "Authorization: Bearer $token\r\nContent-Type: application/json\r\nUser-Agent: PHP-Script\r\n",
                "method"  => "PUT",
                "content" => json_encode($dataMeta),
                "ignore_errors" => true
            ]
        ];
        
        file_get_contents($urlMeta, false, stream_context_create($optionsMeta));

        echo "Upload concluído!";
    } else {
        echo "Erro no envio dos ficheiros no formulário.";
    }
}
?>
