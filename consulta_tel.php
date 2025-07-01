<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// === CONFIGURAÇÃO DE TOKENS ===
$TOKEN_PRODUCAO = ['playboy', 'djhenrique', 'boca', 'aramas'];
$TOKEN_TESTE = 'teste';
$LIMITE_TESTE = 10;

function responder($statusHttp, $conteudo) {
    $conteudo["créditos"] = "@RibeiroDo171";
    http_response_code($statusHttp);
    echo json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// === VALIDAÇÃO DE TOKEN ===
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || (!in_array($token, $TOKEN_PRODUCAO) && $token !== $TOKEN_TESTE)) {
    responder(401, ["status" => false, "mensagem" => "Token inválido ou ausente."]);
}

// === LIMITE PARA TOKEN DE TESTE ===
$arquivo_contador = __DIR__ . "/contador_$token.json";
if ($token === $TOKEN_TESTE) {
    if (!file_exists($arquivo_contador)) file_put_contents($arquivo_contador, json_encode(["consultas" => 0]));
    $contador = json_decode(file_get_contents($arquivo_contador), true);
    if ($contador["consultas"] >= $LIMITE_TESTE) {
        responder(403, ["status" => false, "mensagem" => "Limite de 10 consultas atingido para token de teste."]);
    }
    $contador["consultas"]++;
    file_put_contents($arquivo_contador, json_encode($contador));
}

// === VALIDAÇÃO DE TELEFONE ===
if (!isset($_GET['tel'])) responder(400, ["status" => false, "mensagem" => "Número de telefone não informado."]);
$tel = preg_replace('/\D/', '', $_GET['tel']);
if (strlen($tel) < 10 || strlen($tel) > 11) {
    responder(400, ["status" => false, "mensagem" => "Número de telefone inválido."]);
}

// === CONSULTA COM CURL ===
$url = "https://mdzapis.com/api/consultanew?base=consulta_telefone&query=$tel&apikey=Ribeiro7";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, ["status" => false, "mensagem" => "Erro ao acessar a API externa."]);
}

$data = json_decode($response, true);
$fontes = $data["dados"]["outrasDB"] ?? [];

$resultados = [];

// === COLETAR DADOS DAS FONTES DISPONÍVEIS ===
foreach ($fontes as $fonte => $registros) {
    foreach ($registros as $reg) {
        if (!isset($reg['NOME']) && isset($reg['nome'])) $reg['NOME'] = $reg['nome'];
        if (!isset($reg['CPF']) && isset($reg['doc'])) $reg['CPF'] = $reg['doc'];
        if (!isset($reg['TELEFONE']) && isset($reg['telefone'])) $reg['TELEFONE'] = $reg['telefone'];
        if (!isset($reg['DDD']) && isset($reg['ddd'])) $reg['DDD'] = $reg['ddd'];
        if (!isset($reg['ENDERECO']) && isset($reg['rua'])) $reg['ENDERECO'] = $reg['rua'];
        if (!isset($reg['NUMERO']) && isset($reg['numero'])) $reg['NUMERO'] = $reg['numero'];
        if (!isset($reg['UF']) && isset($reg['uf'])) $reg['UF'] = $reg['uf'];
        if (!isset($reg['CIDADE']) && isset($reg['cidade'])) $reg['CIDADE'] = $reg['cidade'];
        if (!isset($reg['CEP']) && isset($reg['cep'])) $reg['CEP'] = $reg['cep'];
        if (!isset($reg['BAIRRO']) && isset($reg['bairro'])) $reg['BAIRRO'] = $reg['bairro'];

        $resultados[] = [
            "Fonte" => $fonte,
            "Nome" => $reg['NOME'] ?? "---",
            "CPF" => $reg['CPF'] ?? "---",
            "Telefone" => "({$reg['DDD']}) {$reg['TELEFONE']}" ?? $tel,
            "Endereço" => [
                "Rua" => $reg['ENDERECO'] ?? "---",
                "Número" => $reg['NUMERO'] ?? "---",
                "Bairro" => $reg['BAIRRO'] ?? "---",
                "Cidade" => $reg['CIDADE'] ?? "---",
                "UF" => $reg['UF'] ?? "---",
                "CEP" => $reg['CEP'] ?? "---"
            ]
        ];
    }
}

if (empty($resultados)) {
    responder(404, ["status" => false, "mensagem" => "Telefone não encontrado nas bases."]);
}

// === DATA/HORA ===
$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

responder(200, [
    "status" => true,
    "mensagem" => "Dados encontrados com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "quantidade_registros" => count($resultados),
    "resultado" => $resultados
]);
