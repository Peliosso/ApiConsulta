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
    responder(401, [
        "status" => false,
        "mensagem" => "Token inválido ou ausente. Adquira um token válido com @RibeiroDo171."
    ]);
}

// === LIMITAR CONSULTAS DO TOKEN DE TESTE ===
$arquivo_contador = __DIR__ . "/contador_$token.json";
if ($token === $TOKEN_TESTE) {
    if (!file_exists($arquivo_contador)) {
        file_put_contents($arquivo_contador, json_encode(["consultas" => 0]));
    }
    $contador = json_decode(file_get_contents($arquivo_contador), true);
    if ($contador["consultas"] >= $LIMITE_TESTE) {
        responder(403, [
            "status" => false,
            "mensagem" => "Limite de 10 consultas do token de teste atingido. Adquira um token com @RibeiroDo171."
        ]);
    }
    $contador["consultas"]++;
    file_put_contents($arquivo_contador, json_encode($contador));
}

// === VALIDAÇÃO DO CPF ===
if (!isset($_GET['cpf']) || empty($_GET['cpf'])) {
    responder(400, ["status" => false, "mensagem" => "CPF não informado."]);
}
$cpf = preg_replace('/\D/', '', $_GET['cpf']);
if (strlen($cpf) !== 11) {
    responder(400, ["status" => false, "mensagem" => "CPF inválido ou incompleto."]);
}

// === CONSULTA NA API EXTERNA ===
$url = "https://mdzapis.com/api/cpft?cpf=$cpf&apikey=Ribeiro7";
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT => 15,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_USERAGENT => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
    CURLOPT_HTTPHEADER => [
        "Accept: application/json",
        "Referer: https://mdzapis.com/"
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curl_error = curl_error($ch);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, [
        "status" => false,
        "mensagem" => "Erro ao acessar a API externa (cURL bloqueado ou site exige JavaScript).",
        "erro" => $curl_error,
        "http_code" => $http_code
    ]);
}

// === TRATAR RESPOSTA ===
$data = json_decode($response, true);
if (!$data || !$data["status"] || empty($data["dados"])) {
    responder(404, ["status" => false, "mensagem" => "CPF não encontrado ou resposta inválida."]);
}

$d = $data["dados"];
$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

// === MONTAR RESPOSTA FINAL ===
responder(200, [
    "status" => true,
    "mensagem" => "Consulta realizada com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "dados" => [
        "CPF" => $d["cpf"] ?? "---",
        "Nome Completo" => $d["nome"] ?? "---",
        "Nome Social" => $d["nome_social"] ?? "---",
        "Nome da Mãe" => $d["mae"] ?? "---",
        "Nome do Pai" => $d["pai"] ?? "---",
        "Sexo" => $d["sexo"] ?? "---",
        "Raça" => $d["raca"] ?? "---",
        "Data de Nascimento" => $d["data_nascimento"] ?? "---",
        "Tipo Sanguíneo" => $d["tipo_sanguineo"] ?? "---",
        "Nacionalidade" => $d["nacionalidade"] ?? "---",
        "Município de Nascimento" => $d["municipio_nascimento"] ?? "---",
        "Data de Óbito" => $d["data_obito"] ?? "Não encontrado",
        "Número do Documento" => $d["num_documento"] ?? "Não encontrado",
        "Código Sistema Origem" => $d["cod_sistema_origem"] ?? "Não encontrado",
        "Sistema de Origem" => $d["nome_sistema_origem"] ?? "Não encontrado",
        "Motivo" => $d["motivo"] ?? "Não encontrado",
        "Tipo de Logradouro" => $d["tipo_logradouro"] ?? "---",
        "Logradouro" => $d["logradouro"] ?? "---",
        "Número" => $d["numero"] ?? "---",
        "Complemento" => $d["complemento"] ?? "---",
        "Bairro" => $d["bairro"] ?? "---",
        "CEP" => $d["cep"] ?? "---",
        "Município de Residência" => $d["municipio_residencia"] ?? "---",
        "País de Residência" => $d["pais_residencia"] ?? "---",
        "Telefone DDD" => $d["telefone_ddd"] ?? "---",
        "Telefone Número" => $d["telefone_numero"] ?? "---",
        "Telefone Tipo" => $d["telefone_tipo"] ?? "---",
        "CNS" => $d["cns"] ?? "---"
    ]
]);