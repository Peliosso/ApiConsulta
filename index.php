<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

// === CONFIGURAÇÃO DE TOKENS ===
$TOKEN_PRODUCAO = ['playboy', 'djhenrique', 'boca', 'aramas']; // ✅ Array de tokens de produção principal
$TOKEN_TESTE = 'teste';        // Token de teste
$LIMITE_TESTE = 10;

// === FUNÇÃO DE RESPOSTA JSON PADRÃO ===
function responder($statusHttp, $conteudo) {
    $conteudo["créditos"] = "@RibeiroDo171";
    http_response_code($statusHttp);
    echo json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

// === VERIFICAÇÃO DO TOKEN ===
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || (!in_array($token, $TOKEN_PRODUCAO) && $token !== $TOKEN_TESTE)) {
    responder(401, [
        "status" => false,
        "mensagem" => "Token inválido ou ausente. Adquira um token válido com @RibeiroDo171."
    ]);
}

// === CONTROLE DE CONSULTAS DO TOKEN DE TESTE ===
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

// === VALIDAÇÃO E FORMATAÇÃO DO CPF ===
if (!isset($_GET['cpf']) || empty($_GET['cpf'])) {
    responder(400, [
        "status" => false,
        "mensagem" => "CPF não informado."
    ]);
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
if (strlen($cpf) !== 11) {
    responder(400, [
        "status" => false,
        "mensagem" => "CPF inválido ou incompleto."
    ]);
}

// === CONSULTA NA API EXTERNA ===
$url = "https://patronhost.online/apis/cpf.php?cpf=$cpf";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 10);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, ["status" => false, "mensagem" => "Erro ao acessar a API externa (cURL)."]);
}

$response_utf8 = mb_convert_encoding($response, 'UTF-8', 'ISO-8859-1');
$data = json_decode($response_utf8, true);

if (!$data || $data["status"] !== true || $data["resultado"] !== "success") {
    responder(404, [
        "status" => false,
        "mensagem" => "CPF não encontrado ou resposta inválida da API."
    ]);
}

$dados = $data["dados"];

// === CÁLCULO DE IDADE E SIGNO ===
$idade = '---';
$signo = '---';
if (!empty($dados["data_nascimento"])) {
    $nascimento = DateTime::createFromFormat('d/m/Y', $dados["data_nascimento"]);
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
    "mensagem" => "Dados obtidos com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "dados_formatados" => [
        "Nome Completo" => $dados["nome"] ?? "---",
        "Nome Social" => $dados["nome_social"] ?? "---",
        "Mãe" => $dados["mae"] ?? "---",
        "Pai" => $dados["pai"] ?? "---",
        "Sexo" => $dados["sexo"] ?? "---",
        "Raça" => $dados["raca"] ?? "---",
        "Data de Nascimento" => $dados["data_nascimento"] . " ({$idade})",
        "Idade" => $idade,
        "Signo" => $signo,
        "Tipo Sanguíneo" => $dados["tipo_sanguineo"] ?? "---",
        "Nacionalidade" => $dados["nacionalidade"] ?? "---",
        "Município de Nascimento" => $dados["municipio_nascimento"] ?? "---",
        "Data de Óbito" => $dados["data_obito"] ?? "Não encontrado",
        "CPF" => $dados["cpf"] ?? $cpf,
        "Endereço" => [
            "Tipo de Logradouro" => $dados["tipo_logradouro"] ?? "---",
            "Logradouro" => $dados["logradouro"] ?? "---",
            "Número" => $dados["numero"] ?? "---",
            "Complemento" => $dados["complemento"] ?? "---",
            "Bairro" => $dados["bairro"] ?? "---",
            "CEP" => $dados["cep"] ?? "---",
            "Município de Residência" => $dados["municipio_residencia"] ?? "---",
            "País de Residência" => $dados["pais_residencia"] ?? "---"
        ],
        "Telefone" => [
            "DDD" => $dados["telefone_ddd"] ?? "---",
            "Número" => $dados["telefone_numero"] ?? "---"
        ]
    ]
]);

// === FUNÇÃO PARA SIGNO ===
function calcularSigno($dia, $mes) {
    $signos = [
        ['capricórnio', 20], ['aquário', 19], ['peixes', 20], ['áries', 20],
        ['touro', 20], ['gêmeos', 20], ['câncer', 21], ['leão', 22],
        ['virgem', 22], ['libra', 22], ['escorpião', 21], ['sagitário', 21], ['capricórnio', 31]
    ];
    return ($dia <= $signos[$mes - 1][1]) ? ucfirst($signos[$mes - 1][0]) : ucfirst($signos[$mes][0]);
}
