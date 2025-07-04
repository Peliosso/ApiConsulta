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

// === VERIFICAÇÃO DE TOKEN ===
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || (!in_array($token, $TOKEN_PRODUCAO) && $token !== $TOKEN_TESTE)) {
    responder(401, [
        "status" => false,
        "mensagem" => "Token inválido ou ausente. Adquira um token válido com @RibeiroDo171."
    ]);
}

// === LIMITE PARA TOKEN DE TESTE ===
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

// === CONSULTA COM CURL ===
$url = "https://mdzapis.com/api/consultanew?base=cpf_sipni&query=$cpf&apikey=Ribeiro7";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64)');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, ["status" => false, "mensagem" => "Erro ao acessar a API externa."]);
}

$data = json_decode($response, true);
if (!is_array($data) || count($data) === 0 || empty($data[0]['cpf'])) {
    responder(404, ["status" => false, "mensagem" => "CPF não encontrado na base SIPNI."]);
}

$dados = $data[0];

// === CÁLCULO DE IDADE E SIGNO ===
$idade = '---';
$signo = '---';
if (!empty($dados["dataNascimento"])) {
    $nascimento = DateTime::createFromFormat('d/m/Y', $dados["dataNascimento"]);
    if ($nascimento) {
        $hoje = new DateTime();
        $idade = $hoje->diff($nascimento)->y . ' anos';
        $signo = calcularSigno((int)$nascimento->format('d'), (int)$nascimento->format('m'));
    }
}

// === DATA/HORA DA CONSULTA ===
$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

// === RESPOSTA FINAL ===
responder(200, [
    "status" => true,
    "mensagem" => "Dados obtidos com sucesso (SIPNI).",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "dados_formatados" => [
        "Nome Completo" => $dados["nome"] ?? "---",
        "Nome Social" => $dados["nomeSocial"] ?? "---",
        "Mãe" => $dados["nomeMae"] ?? "---",
        "Pai" => $dados["nomePai"] ?? "---",
        "Sexo" => $dados["sexo"] ?? "---",
        "Data de Nascimento" => ($dados["dataNascimento"] ?? "---") . " ({$idade})",
        "Idade" => $idade,
        "Signo" => $signo,
        "CPF" => $dados["cpf"] ?? $cpf,
        "RG" => [
            "Número" => $dados["rgNumero"] ?? "---",
            "Órgão Emissor" => $dados["rgOrgaoEmisor"] ?? "---",
            "UF" => $dados["rgUf"] ?? "---",
            "Data de Emissão" => $dados["rgDataEmissao"] ?? "---"
        ],
        "Endereço" => [
            "Tipo" => $dados["tipoEndereco"] ?? "---",
            "Logradouro" => $dados["logradouro"] ?? "---",
            "Número" => $dados["numero"] ?? "---",
            "Complemento" => $dados["complemento"] ?? "---",
            "Bairro" => $dados["bairro"] ?? "---",
            "Município" => $dados["municipio"] ?? "---",
            "UF" => $dados["siglaUf"] ?? "---",
            "CEP" => $dados["cep"] ?? "---",
            "País" => $dados["pais"] ?? "---"
        ],
        "Telefone" => $dados["telefone"] ?? "---",
        "Ativo" => $dados["ativo"] === "true" ? "Sim" : "Não",
        "Óbito" => $dados["obito"] === "true" ? "Sim" : "Não",
        "CNS Definitivo" => $dados["cnsDefinitivo"] ?? "---",
        "CNS Provisório" => $dados["cnsProvisorio"] ?? "---"
    ]
]);

function calcularSigno($dia, $mes) {
    $signos = [
        ['Capricórnio', 20], ['Aquário', 19], ['Peixes', 20], ['Áries', 20],
        ['Touro', 20], ['Gêmeos', 20], ['Câncer', 21], ['Leão', 22],
        ['Virgem', 22], ['Libra', 22], ['Escorpião', 21], ['Sagitário', 21], ['Capricórnio', 31]
    ];
    return ($dia <= $signos[$mes - 1][1]) ? $signos[$mes - 1][0] : $signos[$mes][0];
}
