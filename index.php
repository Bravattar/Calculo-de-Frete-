<?php
$resultado = null;
$erro = null;
$cupomAplicado = false;
$mensagemCupom = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !empty($_POST['cep'])) {
    $cep = preg_replace('/[^0-9]/', '', $_POST['cep']);
    $cupomDigitado = strtoupper(trim($_POST['cupom'] ?? ''));

    if (strlen($cep) === 8) {
        $url = "https://viacep.com.br/ws/{$cep}/json/";
        $response = @file_get_contents($url);
        $dadosEndereco = json_decode($response, true);

        if ($dadosEndereco && !isset($dadosEndereco['erro'])) {
            $uf = $dadosEndereco['uf'];
            $baseCalculo = ($uf === 'SP') ? 15.00 : 25.00;

            // Lógica do Cupom: Se for 'FRETEFREE', o frete vira 0
            if ($cupomDigitado === 'FRETEFREE') {
                $cupomAplicado = true;
                $mensagemCupom = "Cupom 'FRETEFREE' aplicado com sucesso! 🎉";
                $baseCalculo = 0;
            } elseif (!empty($cupomDigitado)) {
                $mensagemCupom = "Cupom inválido.";
            }

            $resultado = [
                'cidade' => $dadosEndereco['localidade'],
                'uf' => $uf,
                'opcoes' => [
                    [
                        'nome' => $cupomAplicado ? '🎁 Entrega Promocional' : '📦 Econômica (PAC)',
                        'prazo' => $cupomAplicado ? 'Até 5 dias úteis' : 'Até 8 dias úteis',
                        'valor' => $baseCalculo > 0 ? 'R$ ' . number_format($baseCalculo, 2, ',', '.') : 'Grátis',
                        'gratis' => ($baseCalculo == 0)
                    ],
                    [
                        'nome' => '⚡ Expressa (Sedex)',
                        'prazo' => 'Até 2 dias úteis',
                        'valor' => $baseCalculo > 0 ? 'R$ ' . number_format($baseCalculo * 1.8, 2, ',', '.') : 'R$ 12,00',
                        'destaque' => !$cupomAplicado // Destaque muda se o cupom for usado
                    ]
                ]
            ];
        } else {
            $erro = "CEP não encontrado.";
        }
    } else {
        $erro = "Formato de CEP inválido.";
    }
}
?>

<!DOCTYPE html>
<html lang="pt-br">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Cálculo de Frete com Cupom</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-slate-50 p-6 flex justify-center items-start min-h-screen">

    <div class="bg-white p-8 rounded-2xl shadow-xl w-full max-w-md border border-slate-100">
        <h2 class="text-2xl font-bold text-slate-800 mb-6 flex items-center gap-2">
            🚚 <span class="tracking-tight">Calcular Entrega</span>
        </h2>

        <form method="POST" class="space-y-4 mb-6">
            <div>
                <label class="text-xs font-bold text-slate-400 uppercase ml-1">CEP de Destino</label>
                <div class="flex gap-2">
                    <input type="text" name="cep" id="cep" placeholder="00000-000" 
                        value="<?= $_POST['cep'] ?? '' ?>"
                        class="flex-1 border border-slate-300 p-3 rounded-xl focus:ring-2 focus:ring-indigo-500 outline-none transition-all shadow-sm"
                        maxlength="9" required>
                </div>
            </div>

            <div>
                <label class="text-xs font-bold text-slate-400 uppercase ml-1">Cupom de Desconto</label>
                <div class="flex gap-2">
                    <input type="text" name="cupom" placeholder="Ex: FRETEFREE" 
                        value="<?= $_POST['cupom'] ?? '' ?>"
                        class="flex-1 border border-slate-300 p-3 rounded-xl focus:ring-2 focus:ring-emerald-500 outline-none transition-all shadow-sm uppercase">
                    <button type="submit" 
                        class="bg-slate-800 hover:bg-black text-white font-bold px-6 py-3 rounded-xl transition-all active:scale-95 shadow-lg shadow-slate-200">
                        OK
                    </button>
                </div>
                <?php if ($mensagemCupom): ?>
                    <p class="text-[10px] mt-1 ml-1 font-bold <?= $cupomAplicado ? 'text-emerald-600' : 'text-red-500' ?>">
                        <?= $mensagemCupom ?>
                    </p>
                <?php endif; ?>
            </div>
        </form>

        <?php if ($erro): ?>
            <div class="bg-red-50 text-red-600 p-3 rounded-lg text-sm mb-4 border border-red-100 text-center">
                ⚠️ <?= $erro ?>
            </div>
        <?php endif; ?>

        <?php if ($resultado): ?>
            <div class="animate-in slide-in-from-bottom-4 duration-500">
                <p class="text-sm text-slate-500 mb-4 bg-slate-100 p-2 rounded text-center">
                    Enviando para: <strong><?= $resultado['cidade'] ?> - <?= $resultado['uf'] ?></strong>
                </p>

                <div class="space-y-4">
                    <?php foreach ($resultado['opcoes'] as $opcao): ?>
                        <div class="relative border-2 <?= (isset($opcao['gratis']) && $opcao['gratis']) || isset($opcao['destaque']) && $opcao['destaque'] ? 'border-emerald-500 bg-emerald-50' : 'border-slate-100' ?> p-4 rounded-xl transition-all">
                            
                            <?php if (isset($opcao['gratis']) && $opcao['gratis']): ?>
                                <span class="absolute -top-3 right-4 bg-emerald-600 text-white text-[10px] font-black px-3 py-1 rounded-full uppercase tracking-wider shadow-sm">Cupom Ativo</span>
                            <?php endif; ?>

                            <div class="flex justify-between items-center">
                                <div>
                                    <span class="block font-extrabold text-slate-700"><?= $opcao['nome'] ?></span>
                                    <span class="text-xs text-slate-500 uppercase font-semibold"><?= $opcao['prazo'] ?></span>
                                </div>
                                <div class="text-right">
                                    <span class="<?= (isset($opcao['gratis']) && $opcao['gratis']) ? 'text-emerald-600' : 'text-slate-900' ?> font-black text-lg">
                                        <?= $opcao['valor'] ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <script>
        // Máscara de CEP
        document.getElementById('cep').addEventListener('input', function (e) {
            var x = e.target.value.replace(/\D/g, '').match(/(\d{0,5})(\d{0,3})/);
            e.target.value = !x[2] ? x[1] : x[1] + '-' + x[2];
        });
    </script>

</body>
</html>