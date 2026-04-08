<?php
require_once 'config.php';

$resultado = null;
$erro = null;
$cupomAtivo = false;
$isApiRequest = (($_GET['api'] ?? '') === '1');

function adicionarDiasUteis(DateTime $dataBase, int $diasUteis): DateTime {
    $data = clone $dataBase;

    while ($diasUteis > 0) {
        $data->modify('+1 day');
        if (ehDiaUtil($data)) {
            $diasUteis--;
        }
    }

    return $data;
}

function ehDiaUtil(DateTime $data): bool {
    $diaSemana = (int) $data->format('N'); // 1 (seg) a 7 (dom)
    if ($diaSemana >= 6) {
        return false;
    }

    $feriados = obterFeriadosNacionais((int) $data->format('Y'));
    return !isset($feriados[$data->format('Y-m-d')]);
}

function obterFeriadosNacionais(int $ano): array {
    static $cache = [];
    if (isset($cache[$ano])) {
        return $cache[$ano];
    }

    $pascoa = new DateTime($ano . '-03-21');
    $pascoa->modify('+' . easter_days($ano) . ' days');
    $sextaSanta = (clone $pascoa)->modify('-2 days')->format('Y-m-d');

    $feriados = [
        $ano . '-01-01', // Confraternizacao Universal
        $sextaSanta, // Paixao de Cristo
        $ano . '-04-21', // Tiradentes
        $ano . '-05-01', // Dia do Trabalhador
        $ano . '-09-07', // Independencia do Brasil
        $ano . '-10-12', // Nossa Senhora Aparecida
        $ano . '-11-02', // Finados
        $ano . '-11-15', // Proclamacao da Republica
        $ano . '-11-20', // Dia da Consciencia Negra
        $ano . '-12-25' // Natal
    ];

    $cache[$ano] = array_fill_keys($feriados, true);
    return $cache[$ano];
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep'] ?? '');
    $latUser = $_POST['lat_user'] ?? null;
    $lngUser = $_POST['lng_user'] ?? null;
    $cupomInput = strtoupper(trim($_POST['cupom'] ?? ''));

    if (strlen($cep) === 8) {
        if (!empty($latUser) && !empty($lngUser)) {
            $distanciaKm = calcularDistanciaMatematica(LOJA_LAT, LOJA_LNG, $latUser, $lngUser);
            $custoBase = $distanciaKm * PRECO_POR_KM;
            if ($custoBase < VALOR_MINIMO) {
                $custoBase = VALOR_MINIMO;
            }
            $origemDados = 'Localizacao precisa via GPS (' . round($distanciaKm, 1) . ' km)';
        } else {
            $custoBase = 15.00;
            $origemDados = "CEP: $cep (Localizacao Estimada)";
        } 
        
        if ($cupomInput === CUPOM_FRETE_GRATIS) {
            $cupomAtivo = true;
        }

        $hoje = new DateTime('today');

        $valorRetirada = 0.00;
        $valorNormal = $cupomAtivo ? 0.00 : $custoBase;
        $valorFull = $cupomAtivo ? 12.00 : ($custoBase * 1.2);

        $entregaRetirada = adicionarDiasUteis($hoje, 4)->format('d/m/Y');
        $entregaFull = adicionarDiasUteis($hoje, 2)->format('d/m/Y');
        $entregaNormal = adicionarDiasUteis($hoje, 7)->format('d/m/Y');

        $resultado = [
            'info' => $origemDados,
            'opcoes' => [
                [
                    'id' => 'retirada_agencia',
                    'nome' => 'Retirar em uma agencia',
                    'entrega' => $entregaRetirada,
                    'valor' => 'R$ ' . number_format($valorRetirada, 2, ',', '.')
                ],
                [
                    'id' => 'frete_full',
                    'nome' => 'Frete Full',
                    'entrega' => $entregaFull,
                    'valor' => 'R$ ' . number_format($valorFull, 2, ',', '.')
                ],
                [
                    'id' => 'frete_normal',
                    'nome' => 'Frete normal',
                    'entrega' => $entregaNormal,
                    'valor' => 'R$ ' . number_format($valorNormal, 2, ',', '.')
                ]
            ]
        ];
    } else {
        $erro = 'CEP invalido. Digite 8 numeros.';
    }
}

if ($isApiRequest) {
    header('Content-Type: application/json; charset=UTF-8');

    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        http_response_code(405);
        echo json_encode(['ok' => false, 'erro' => 'Metodo nao permitido.'], JSON_UNESCAPED_UNICODE);
        exit;
    }

    if ($erro) {
        echo json_encode(['ok' => false, 'erro' => $erro], JSON_UNESCAPED_UNICODE);
        exit;
    }

    echo json_encode(['ok' => true, 'resultado' => $resultado], JSON_UNESCAPED_UNICODE);
    exit;
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Smart Logistics - ADS Project</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel='stylesheet' href='tema.css'>
</head>
<body class="flex justify-center items-center min-h-screen p-4">

    <div class="glass-ui p-8 rounded-[2rem] w-full max-w-md shadow-2xl text-white border border-slate-700">
        <header class="text-center mb-10">
            <div class="bg-indigo-500/20 w-16 h-16 rounded-2xl flex items-center justify-center mx-auto mb-4">
                <i class="fas fa-location-crosshairs text-indigo-400 text-2xl"></i>
            </div>
            <h1 class="text-2xl font-black tracking-tight">Calculo de Entrega</h1>
        </header>

        <button onclick="detectarLocalizacao()" id="btnLoc" class="w-full mb-8 bg-white text-slate-900 font-black py-4 rounded-2xl flex items-center justify-center gap-3 btn-animate shadow-xl">
            <span id="locIcon">📍</span> <span id="locText">LOCALIZACAO ATUAL</span>
        </button>

        <form method="POST" id="formMaster" class="space-y-5">
            <input type="hidden" name="lat_user" id="lat_user">
            <input type="hidden" name="lng_user" id="lng_user">
            <input type="hidden" name="opcao_final" id="opcao_final">

            <div>
                <label class="text-[10px] font-black text-slate-500 uppercase ml-2 mb-2 block">CEP de Destino</label>
                <input type="text" name="cep" id="cep" placeholder="00000-000 ou 00.000-000" maxlength="10" required
                    value="<?= $_POST['cep'] ?? '' ?>"
                    class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl outline-none focus:ring-2 focus:ring-indigo-500 text-lg font-bold transition-all">
            </div>

            <div class="flex gap-3">
                <div class="flex-1">
                    <label class="text-[10px] font-black text-slate-500 uppercase ml-2 mb-2 block">Cupom</label>
                    <input type="text" name="cupom" placeholder="FRETEFREE" value="<?= $_POST['cupom'] ?? '' ?>"
                        class="w-full bg-slate-900/50 border border-slate-700 p-4 rounded-2xl outline-none uppercase text-sm font-bold">
                </div>
                <button type="submit" class="bg-indigo-600 hover:bg-indigo-500 px-8 rounded-2xl font-black btn-animate shadow-lg shadow-indigo-900/40">
                    CALCULAR
                </button>
            </div>
        </form>

        <?php if ($erro): ?>
            <div class="mt-6 p-4 bg-red-500/10 border border-red-500/50 text-red-400 text-xs rounded-xl text-center font-bold">
                ⚠️ <?= $erro ?>
            </div>
        <?php endif; ?>

        <?php if ($resultado): ?>
            <div class="mt-10 animate-in fade-in slide-in-from-bottom-4 duration-500">
                <div class="text-center mb-4">
                    <span class="bg-slate-800 text-slate-400 text-[9px] px-3 py-1 rounded-full font-black uppercase"><?= $resultado['info'] ?></span>
                </div>

                <div class="space-y-3">
                    <?php foreach ($resultado['opcoes'] as $opcao): ?>
                        <div onclick="selecionarFrete(this, '<?= $opcao['id'] ?>')"
                             class="shipping-card border border-slate-700 p-5 rounded-3xl cursor-pointer flex justify-between items-center transition-all hover:border-slate-500">
                            <div>
                                <p class="font-black text-slate-100"><?= $opcao['nome'] ?></p>
                                <p class="text-[10px] text-slate-400 font-bold mt-1 tracking-wider uppercase">Entrega em: <?= $opcao['entrega'] ?></p>
                            </div>
                            <span class="font-black text-indigo-400 text-lg"><?= $opcao['valor'] ?></span>
                        </div>
                    <?php endforeach; ?>

                    <button id="btnCheckout" class="w-full mt-6 bg-emerald-500 py-5 rounded-3xl font-black hidden btn-animate shadow-xl shadow-emerald-900/30">
                        FINALIZAR SELECAO
                    </button>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        async function detectarLocalizacao() {
            const btn = document.getElementById('btnLoc');
            const text = document.getElementById('locText');

            if (!navigator.geolocation) return alert('Erro: GPS indisponivel.');

            text.innerText = 'LOCALIZANDO...';
            btn.classList.add('opacity-50');

            navigator.geolocation.getCurrentPosition(async (pos) => {
                const lat = pos.coords.latitude;
                const lng = pos.coords.longitude;
                document.getElementById('lat_user').value = lat;
                document.getElementById('lng_user').value = lng;

                try {
                    const resp = await fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`);
                    const data = await resp.json();
                    if (data.address && data.address.postcode) {
                        const rawCep = data.address.postcode.replace(/\D/g, '');
                        document.getElementById('cep').value = rawCep.slice(0, 5) + '-' + rawCep.slice(5, 8);
                        setTimeout(() => document.getElementById('formMaster').submit(), 600);
                    }
                } catch (e) {
                    text.innerText = 'GPS ATIVO (CEP MANUAL)';
                }
            }, () => {
                text.innerText = 'ACESSO NEGADO';
                btn.classList.remove('opacity-50');
            });
        }

        function selecionarFrete(el, id) {
            document.querySelectorAll('.shipping-card').forEach((c) => c.classList.remove('selected'));
            el.classList.add('selected');
            document.getElementById('opcao_final').value = id;
            document.getElementById('btnCheckout').classList.remove('hidden');
        }

        function normalizarCep(valor) {
            const digitos = String(valor).replace(/\D/g, '').slice(0, 8);
            if (digitos.length > 5) {
                return `${digitos.slice(0, 5)}-${digitos.slice(5)}`;
            }
            return digitos;
        }

        document.getElementById('cep').addEventListener('input', (e) => {
            e.target.value = normalizarCep(e.target.value);
        });
    </script>
</body>
</html>
