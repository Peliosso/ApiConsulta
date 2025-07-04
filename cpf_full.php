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

// === VERIFICAÇÃO DO TOKEN ===
$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || (!in_array($token, $TOKEN_PRODUCAO) && $token !== $TOKEN_TESTE)) {
    responder(401, [
        "status" => false,
        "mensagem" => "Token inválido ou ausente. Adquira um token válido com @RibeiroDo171."
    ]);
}

// === CONTROLE DE CONSULTAS PARA TESTE ===
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

// === VALIDAÇÃO DO PARÂMETRO ===
$cpf = preg_replace('/\D/', '', $_GET['cpf'] ?? '');
if (strlen($cpf) !== 11) {
    responder(400, ["status" => false, "mensagem" => "CPF inválido ou não informado."]);
}

// === CONSULTA COM CURL ===
$url = "https://mdzapis.com/api/consultanew?base=consulta_cpf_cc&query={$cpf}&apikey=Ribeiro7";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0');
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, ["status" => false, "mensagem" => "Erro ao acessar a API externa."]);
}

$data = json_decode($response, true);
$dados = $data['dados']['DADOS'] ?? null;

if (!$dados) {
    responder(404, ["status" => false, "mensagem" => "CPF não encontrado na base."]);
}

// === DATA/HORA ===
$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

// === PROCESSAMENTO ===
$nascimento = $dados['NASC'] ?? null;
$idade = "---";
$signo = "---";

if (!empty($nascimento)) {
    $dt = new DateTime(substr($nascimento, 0, 10));
    $idade = (new DateTime())->diff($dt)->y . " anos";
    $signo = calcularSigno((int)$dt->format('d'), (int)$dt->format('m'));
}

$resposta = [
    "status" => true,
    "mensagem" => "Consulta CPF Full realizada com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "cpf" => $dados['CPF'] ?? "---",
    "nome" => $dados['NOME'] ?? "---",
    "mae" => $dados['NOME_MAE'] ?? "---",
    "pai" => $dados['NOME_PAI'] ?? "Não informado",
    "sexo" => $dados['SEXO'] ?? "---",
    "data_nascimento" => isset($dt) ? $dt->format('d/m/Y') : "---",
    "idade" => $idade,
    "signo" => $signo,
    "renda" => $dados['RENDA'] ?? "---",
    "titulo_eleitor" => $dados['TITULO_ELEITOR'] ?? "---"
];

// === RESPOSTA FINAL ===
responder(200, $resposta);

// === FUNÇÃO DE SIGNO ===
function calcularSigno($dia, $mes) {
    $signos = [
        ['Capricórnio', 20], ['Aquário', 19], ['Peixes', 20], ['Áries', 20],
        ['Touro', 20], ['Gêmeos', 20], ['Câncer', 21], ['Leão', 22],
        ['Virgem', 22], ['Libra', 22], ['Escorpião', 21], ['Sagitário', 21], ['Capricórnio', 31]
    ];
    return ($dia <= $signos[$mes - 1][1]) ? $signos[$mes - 1][0] : $signos[$mes][0];
}
