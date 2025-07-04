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
if (!isset($_GET['nome']) || empty($_GET['nome'])) {
    responder(400, ["status" => false, "mensagem" => "Nome não informado."]);
}

$nome = urlencode($_GET['nome']);

// === CONSULTA COM CURL ===
$url = "https://mdzapis.com/api/consultanew?base=nome_abreviado&query={$nome}&apikey=Ribeiro7";
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
$resultados = $data["RESULTADOS"] ?? [];

if (!is_array($resultados) || count($resultados) === 0) {
    responder(404, ["status" => false, "mensagem" => "Nenhum resultado encontrado para o nome informado."]);
}

// === DATA/HORA ===
$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

// === FORMATAR RESULTADOS ===
$resultados_formatados = [];

foreach ($resultados as $item) {
    $nasc = $item["NASC"] ?? null;
    $idade = "---";
    $signo = "---";

    if (!empty($nasc) && $nasc !== "0000-00-00 00:00:00.000") {
        $dataNasc = DateTime::createFromFormat('Y-m-d H:i:s.v', $nasc);
        if ($dataNasc) {
            $idade = (new DateTime())->diff($dataNasc)->y . " anos";
            $signo = calcularSigno((int)$dataNasc->format('d'), (int)$dataNasc->format('m'));
        }
    }

    $resultados_formatados[] = [
        "Nome" => $item["NOME"] ?? "---",
        "CPF" => $item["CPF"] ?? "---",
        "Sexo" => $item["SEXO"] ?? "---",
        "Nome da Mãe" => $item["NOME_MAE"] ?? "---",
        "Data de Nascimento" => isset($dataNasc) ? $dataNasc->format('d/m/Y') : "---",
        "Idade" => $idade,
        "Signo" => $signo
    ];
}

// === RESPOSTA ===
responder(200, [
    "status" => true,
    "mensagem" => "Consulta por nome realizada com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "quantidade_resultados" => count($resultados_formatados),
    "resultados" => $resultados_formatados
]);

// === FUNÇÃO DE SIGNO ===
function calcularSigno($dia, $mes) {
    $signos = [
        ['Capricórnio', 20], ['Aquário', 19], ['Peixes', 20], ['Áries', 20],
        ['Touro', 20], ['Gêmeos', 20], ['Câncer', 21], ['Leão', 22],
        ['Virgem', 22], ['Libra', 22], ['Escorpião', 21], ['Sagitário', 21], ['Capricórnio', 31]
    ];
    return ($dia <= $signos[$mes - 1][1]) ? $signos[$mes - 1][0] : $signos[$mes][0];
}
