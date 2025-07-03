<?php
session_start();
header("Content-Type: application/json; charset=utf-8");
header("Access-Control-Allow-Origin: *");

$TOKEN_PRODUCAO = ['playboy', 'djhenrique', 'boca', 'aramas'];
$TOKEN_TESTE = 'teste';
$LIMITE_TESTE = 10;

function responder($statusHttp, $conteudo) {
    $conteudo["créditos"] = "@RibeiroDo171";
    http_response_code($statusHttp);
    echo json_encode($conteudo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
    exit;
}

$token = $_GET['token'] ?? $_SERVER['HTTP_AUTHORIZATION'] ?? '';
if (empty($token) || (!in_array($token, $TOKEN_PRODUCAO) && $token !== $TOKEN_TESTE)) {
    responder(401, [
        "status" => false,
        "mensagem" => "Token inválido ou ausente. Adquira um token válido com @RibeiroDo171."
    ]);
}

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

if (!isset($_GET['cpf']) || empty($_GET['cpf'])) {
    responder(400, ["status" => false, "mensagem" => "CPF não informado."]);
}

$cpf = preg_replace('/\D/', '', $_GET['cpf']);
if (strlen($cpf) !== 11) {
    responder(400, ["status" => false, "mensagem" => "CPF inválido ou incompleto."]);
}

$url = "https://mdzapis.com/api/consultanew?base=cpf_serasa_completo&query=$cpf&apikey=Ribeiro7";
$ch = curl_init($url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

if (!$response || $http_code !== 200) {
    responder(500, ["status" => false, "mensagem" => "Erro ao acessar a API externa."]);
}

$data = json_decode($response, true);
if (!$data || !isset($data["dados_pessoais"])) {
    responder(404, ["status" => false, "mensagem" => "CPF não encontrado ou resposta inválida da API."]);
}

$pessoal = $data["dados_pessoais"];
$enderecos = $data["enderecos"] ?? [];
$parentes = $data["parentes"] ?? [];
$telefones = $data["telefones"] ?? [];
$emails = $data["emails"] ?? [];
$score = $data["score"] ?? [];
$poder = $data["poder_aquisitivo"] ?? [];

$idade = "---";
$signo = "---";
if (!empty($pessoal["data_nascimento"])) {
    $nasc = DateTime::createFromFormat('d/m/Y', $pessoal["data_nascimento"]);
    if ($nasc) {
        $hoje = new DateTime();
        $idade = $hoje->diff($nasc)->y . " anos";
        $signo = calcularSigno((int)$nasc->format("d"), (int)$nasc->format("m"));
    }
}

$data_consulta = date('d/m/Y');
$hora_consulta = date('H:i:s');

responder(200, [
    "status" => true,
    "mensagem" => "Dados encontrados com sucesso.",
    "data_consulta" => $data_consulta,
    "hora_consulta" => $hora_consulta,
    "nome" => $pessoal["nome"] ?? "---",
    "cpf" => $pessoal["cpf"] ?? $cpf,
    "idade" => $idade,
    "signo" => $signo,
    "sexo" => $pessoal["sexo"] ?? "---",
    "data_nascimento" => $pessoal["data_nascimento"] ?? "---",
    "mae" => $pessoal["nome_mae"] ?? "---",
    "pai" => $pessoal["nome_pai"] ?? "---",
    "titulo_eleitor" => $pessoal["titulo_eleitor"] ?? "---",
    "nacionalidade" => $pessoal["nacionalidade"] ?? "---",
    "renda" => $pessoal["renda"] ?? "---",
    "faixa_renda" => $pessoal["faixa_renda"]["descricao"] ?? "---",
    "mosaic" => [
        "principal" => $pessoal["codigo_mosaic"]["principal"]["descricao"] ?? "---",
        "novo" => $pessoal["codigo_mosaic"]["novo"]["descricao"] ?? "---",
        "secundario" => $pessoal["codigo_mosaic"]["secundario"]["descricao"] ?? "---"
    ],
    "emails" => $emails,
    "score" => [
        "CSB8" => $score["CSB8"] ?? "---",
        "CSB8_FAIXA" => $score["CSB8_FAIXA"] ?? "---",
        "CSBA" => $score["CSBA"] ?? "---",
        "CSBA_FAIXA" => $score["CSBA_FAIXA"] ?? "---"
    ],
    "poder_aquisitivo" => [
        "PODER_AQUISITIVO" => $poder["PODER_AQUISITIVO"] ?? "---",
        "RENDA_PODER_AQUISITIVO" => $poder["RENDA_PODER_AQUISITIVO"] ?? "---",
        "FX_PODER_AQUISITIVO" => $poder["FX_PODER_AQUISITIVO"] ?? "---"
    ],
    "enderecos" => $enderecos,
    "telefones" => array_map(function($tel) {
        return [
            "DDD" => $tel["DDD"] ?? "---",
            "TELEFONE" => $tel["TELEFONE"] ?? "---",
            "TIPO" => $tel["TIPO_TELEFONE"] ?? "---",
            "CLASSIFICACAO" => $tel["CLASSIFICACAO"] ?? "---"
        ];
    }, $telefones),
    "parentes" => array_map(function($p) {
        return [
            "nome" => $p["NOME_VINCULO"] ?? "---",
            "vinculo" => $p["VINCULO"] ?? "---",
            "cpf" => $p["CPF_VINCULO"] ?? "---"
        ];
    }, $parentes)
]);

function calcularSigno($dia, $mes) {
    $signos = [
        ['Capricórnio', 20], ['Aquário', 19], ['Peixes', 20], ['Áries', 20],
        ['Touro', 20], ['Gêmeos', 20], ['Câncer', 21], ['Leão', 22],
        ['Virgem', 22], ['Libra', 22], ['Escorpião', 21], ['Sagitário', 21], ['Capricórnio', 31]
    ];
    return ($dia <= $signos[$mes - 1][1]) ? $signos[$mes - 1][0] : $signos[$mes][0];
}
