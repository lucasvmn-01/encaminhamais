<?php
// Página de Aniversariantes - Salvar como aniversario_cliente.php

session_start();

// Inclui as configurações
require_once 'config/database.php';
require_once 'config/functions.php';

// Verifica se o usuário está logado
requireLogin();

$db = getDB();

// Obtém filtros
$mes = intval($_GET['mes'] ?? date('n'));
$ano = intval($_GET['ano'] ?? date('Y'));

// Busca aniversariantes do mês
$aniversariantes = $db->select("
    SELECT *,
           DAY(data_nascimento) as dia_aniversario,
           TIMESTAMPDIFF(YEAR, data_nascimento, CURDATE()) as idade_atual,
           TIMESTAMPDIFF(YEAR, data_nascimento, CONCAT(?, '-', LPAD(MONTH(data_nascimento), 2, '0'), '-', LPAD(DAY(data_nascimento), 2, '0'))) as idade_no_aniversario
    FROM clientes 
    WHERE MONTH(data_nascimento) = ?
    ORDER BY DAY(data_nascimento), nome_completo
", [$ano, $mes]);

// Estatísticas
$totalAniversariantes = count($aniversariantes);
$aniversariantesHoje = array_filter($aniversariantes, function($cliente) {
    return intval($cliente['dia_aniversario']) === intval(date('j'));
});
$totalHoje = count($aniversariantesHoje);

// Aniversariantes que aceitam parabéns
$aceitamParabens = array_filter($aniversariantes, function($cliente) {
    return $cliente['aceita_parabens'] == 1;
});
$totalAceitamParabens = count($aceitamParabens);

// Próximos aniversários (próximos 7 dias)
$proximosAniversarios = [];
$hoje = intval(date('j'));
$mesAtual = intval(date('n'));

if ($mes === $mesAtual) {
    $proximosAniversarios = array_filter($aniversariantes, function($cliente) use ($hoje) {
        $diaAniversario = intval($cliente['dia_aniversario']);
        return $diaAniversario >= $hoje && $diaAniversario <= ($hoje + 7);
    });
}

$pageTitle = 'Aniversariantes';
$pageSubtitle = 'Clientes que fazem aniversário em ' . getMonthName($mes) . ' de ' . $ano;

include 'templates/header.php';
?>

<div class="flex h-screen bg-gray-100 dark:bg-gray-900">
    
    <?php include 'templates/sidebar.php'; ?>
    
    <div class="flex-1 flex flex-col overflow-hidden">
        
        <?php include 'templates/topbar.php'; ?>
        
        <main class="flex-1 overflow-x-hidden overflow-y-auto bg-gray-100 dark:bg-gray-900 p-6">
            
            <!-- Submenu -->
            <div class="mb-6">
                <nav class="flex space-x-4">
                    <a href="clientes.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Lista de Clientes
                    </a>
                    <a href="aniversario_cliente.php" class="px-4 py-2 bg-blue-600 text-white rounded-lg font-medium">
                        Aniversariantes
                    </a>
                    <a href="documentos_clientes.php" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg font-medium hover:bg-gray-300 dark:hover:bg-gray-600">
                        Documentos
                    </a>
                </nav>
            </div>
            
            <!-- Filtros -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 p-6 mb-6">
                <div class="flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    
                    <div class="flex items-center gap-4">
                        <h2 class="text-xl font-semibold text-gray-900 dark:text-white">
                            🎂 Aniversariantes
                        </h2>
                    </div>
                    
                    <form method="GET" class="flex items-center gap-3">
                        <div>
                            <label for="mes" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Mês
                            </label>
                            <select id="mes" name="mes" class="input-destacado">
                                <?php for ($m = 1; $m <= 12; $m++): ?>
                                    <option value="<?php echo $m; ?>" <?php echo $m === $mes ? 'selected' : ''; ?>>
                                        <?php echo getMonthName($m); ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div>
                            <label for="ano" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-1">
                                Ano
                            </label>
                            <select id="ano" name="ano" class="input-destacado">
                                <?php for ($a = date('Y') - 5; $a <= date('Y') + 5; $a++): ?>
                                    <option value="<?php echo $a; ?>" <?php echo $a === $ano ? 'selected' : ''; ?>>
                                        <?php echo $a; ?>
                                    </option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        
                        <div class="pt-6">
                            <button type="submit" class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i data-lucide="search" class="w-4 h-4"></i>
                                Filtrar
                            </button>
                        </div>
                    </form>
                    
                </div>
            </div>
            
            <!-- Estatísticas -->
            <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-8">
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Total no Mês</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalAniversariantes; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-100 dark:bg-blue-900 rounded-full flex items-center justify-center">
                            <i data-lucide="cake" class="w-6 h-6 text-blue-600 dark:text-blue-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Hoje</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalHoje; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-yellow-100 dark:bg-yellow-900 rounded-full flex items-center justify-center">
                            <i data-lucide="gift" class="w-6 h-6 text-yellow-600 dark:text-yellow-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Aceitam Parabéns</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo $totalAceitamParabens; ?></p>
                        </div>
                        <div class="w-12 h-12 bg-green-100 dark:bg-green-900 rounded-full flex items-center justify-center">
                            <i data-lucide="message-circle" class="w-6 h-6 text-green-600 dark:text-green-400"></i>
                        </div>
                    </div>
                </div>
                
                <div class="dashboard-card">
                    <div class="flex items-center justify-between">
                        <div>
                            <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Próximos 7 dias</p>
                            <p class="text-3xl font-bold text-gray-900 dark:text-white"><?php echo count($proximosAniversarios); ?></p>
                        </div>
                        <div class="w-12 h-12 bg-purple-100 dark:bg-purple-900 rounded-full flex items-center justify-center">
                            <i data-lucide="calendar" class="w-6 h-6 text-purple-600 dark:text-purple-400"></i>
                        </div>
                    </div>
                </div>
                
            </div>
            
            <!-- Aniversariantes de Hoje -->
            <?php if (!empty($aniversariantesHoje)): ?>
                <div class="bg-gradient-to-r from-yellow-50 to-orange-50 dark:from-yellow-900/20 dark:to-orange-900/20 rounded-xl border border-yellow-200 dark:border-yellow-800 p-6 mb-8">
                    <div class="flex items-center gap-3 mb-4">
                        <div class="w-10 h-10 bg-yellow-500 rounded-full flex items-center justify-center">
                            <i data-lucide="party-popper" class="w-5 h-5 text-white"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                                🎉 Aniversariantes de Hoje!
                            </h3>
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Que tal enviar uma mensagem especial?
                            </p>
                        </div>
                    </div>
                    
                    <div class="grid gap-4">
                        <?php foreach ($aniversariantesHoje as $aniversariante): ?>
                            <div class="bg-white dark:bg-gray-800 rounded-lg p-4 border border-yellow-200 dark:border-yellow-700">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center gap-3">
                                        <?php if ($aniversariante['foto_path'] && file_exists($aniversariante['foto_path'])): ?>
                                            <img src="<?php echo htmlspecialchars($aniversariante['foto_path']); ?>" 
                                                 class="w-12 h-12 rounded-full object-cover" 
                                                 alt="Foto">
                                        <?php else: ?>
                                            <div class="w-12 h-12 bg-yellow-200 dark:bg-yellow-800 rounded-full flex items-center justify-center">
                                                <span class="text-sm font-semibold text-yellow-800 dark:text-yellow-200">
                                                    <?php echo strtoupper(substr($aniversariante['nome_completo'], 0, 1)); ?>
                                                </span>
                                            </div>
                                        <?php endif; ?>
                                        
                                        <div>
                                            <p class="font-semibold text-gray-900 dark:text-white">
                                                <?php echo htmlspecialchars($aniversariante['nome_completo']); ?>
                                            </p>
                                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                                Fazendo <?php echo $aniversariante['idade_no_aniversario']; ?> anos hoje! 🎂
                                            </p>
                                        </div>
                                    </div>
                                    
                                    <div class="flex items-center gap-2">
                                        <?php if ($aniversariante['telefone1'] && $aniversariante['aceita_parabens']): ?>
                                            <a href="<?php echo getWhatsAppLink($aniversariante['telefone1'], 'Parabéns pelo seu aniversário! 🎉🎂 Desejamos um dia repleto de alegrias e realizações!'); ?>" 
                                               target="_blank"
                                               class="inline-flex items-center gap-2 px-3 py-2 bg-green-600 text-white text-sm rounded-lg hover:bg-green-700">
                                                <i data-lucide="message-circle" class="w-4 h-4"></i>
                                                Parabenizar
                                            </a>
                                        <?php endif; ?>
                                        
                                        <a href="cliente_editar.php?id=<?php echo $aniversariante['id']; ?>" 
                                           class="inline-flex items-center gap-2 px-3 py-2 bg-blue-600 text-white text-sm rounded-lg hover:bg-blue-700">
                                            <i data-lucide="edit" class="w-4 h-4"></i>
                                            Editar
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Próximos Aniversários -->
            <?php if (!empty($proximosAniversarios) && $mes === intval(date('n'))): ?>
                <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700 mb-8">
                    <div class="p-6 border-b dark:border-gray-700">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white flex items-center gap-2">
                            <i data-lucide="calendar-days" class="w-5 h-5"></i>
                            Próximos Aniversários (7 dias)
                        </h3>
                    </div>
                    <div class="p-6">
                        <div class="grid gap-3">
                            <?php foreach ($proximosAniversarios as $proximo): ?>
                                <?php if (intval($proximo['dia_aniversario']) !== intval(date('j'))): // Não mostrar os de hoje novamente ?>
                                    <div class="flex items-center justify-between p-3 bg-blue-50 dark:bg-blue-900/20 rounded-lg">
                                        <div class="flex items-center gap-3">
                                            <?php if ($proximo['foto_path'] && file_exists($proximo['foto_path'])): ?>
                                                <img src="<?php echo htmlspecialchars($proximo['foto_path']); ?>" 
                                                     class="w-10 h-10 rounded-full object-cover" 
                                                     alt="Foto">
                                            <?php else: ?>
                                                <div class="w-10 h-10 bg-blue-200 dark:bg-blue-800 rounded-full flex items-center justify-center">
                                                    <span class="text-sm font-semibold text-blue-800 dark:text-blue-200">
                                                        <?php echo strtoupper(substr($proximo['nome_completo'], 0, 1)); ?>
                                                    </span>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div>
                                                <p class="font-medium text-gray-900 dark:text-white">
                                                    <?php echo htmlspecialchars($proximo['nome_completo']); ?>
                                                </p>
                                                <p class="text-sm text-gray-600 dark:text-gray-400">
                                                    <?php echo $proximo['dia_aniversario']; ?> de <?php echo getMonthName($mes); ?> - 
                                                    <?php echo $proximo['idade_no_aniversario']; ?> anos
                                                </p>
                                            </div>
                                        </div>
                                        
                                        <div class="text-sm text-blue-600 dark:text-blue-400 font-medium">
                                            <?php 
                                            $diasRestantes = intval($proximo['dia_aniversario']) - intval(date('j'));
                                            echo $diasRestantes === 1 ? 'Amanhã' : "Em $diasRestantes dias";
                                            ?>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            
            <!-- Lista Completa de Aniversariantes -->
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border dark:border-gray-700">
                <div class="p-6 border-b dark:border-gray-700">
                    <div class="flex items-center justify-between">
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white">
                            Todos os Aniversariantes de <?php echo getMonthName($mes); ?>
                        </h3>
                        
                        <div class="flex items-center gap-3">
                            <button onclick="sendBulkMessages()" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-green-600 text-white rounded-lg hover:bg-green-700">
                                <i data-lucide="send" class="w-4 h-4"></i>
                                Enviar Parabéns em Massa
                            </button>
                            
                            <button onclick="exportBirthdays()" 
                                    class="inline-flex items-center gap-2 px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700">
                                <i data-lucide="download" class="w-4 h-4"></i>
                                Exportar Lista
                            </button>
                        </div>
                    </div>
                </div>
                
                <?php if (empty($aniversariantes)): ?>
                    <div class="p-12 text-center">
                        <i data-lucide="cake" class="w-16 h-16 text-gray-400 mx-auto mb-4"></i>
                        <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-2">
                            Nenhum aniversariante encontrado
                        </h3>
                        <p class="text-gray-600 dark:text-gray-400">
                            Não há clientes fazendo aniversário em <?php echo getMonthName($mes); ?> de <?php echo $ano; ?>.
                        </p>
                    </div>
                <?php else: ?>
                    <div class="overflow-x-auto">
                        <table class="table-custom">
                            <thead>
                                <tr>
                                    <th>Dia</th>
                                    <th>Cliente</th>
                                    <th>Idade</th>
                                    <th>Telefone</th>
                                    <th>Aceita Parabéns</th>
                                    <th>Ações</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($aniversariantes as $cliente): ?>
                                    <?php 
                                    $isToday = intval($cliente['dia_aniversario']) === intval(date('j')) && $mes === intval(date('n'));
                                    $rowClass = $isToday ? 'bg-yellow-50 dark:bg-yellow-900/20' : 'hover:bg-gray-50 dark:hover:bg-gray-700';
                                    ?>
                                    <tr class="<?php echo $rowClass; ?>">
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <span class="font-semibold text-lg">
                                                    <?php echo $cliente['dia_aniversario']; ?>
                                                </span>
                                                <?php if ($isToday): ?>
                                                    <span class="px-2 py-1 bg-yellow-500 text-white text-xs rounded-full">
                                                        HOJE
                                                    </span>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-3">
                                                <?php if ($cliente['foto_path'] && file_exists($cliente['foto_path'])): ?>
                                                    <img src="<?php echo htmlspecialchars($cliente['foto_path']); ?>" 
                                                         class="w-8 h-8 rounded-full object-cover" 
                                                         alt="Foto">
                                                <?php else: ?>
                                                    <div class="w-8 h-8 bg-gray-300 dark:bg-gray-600 rounded-full flex items-center justify-center">
                                                        <span class="text-xs font-semibold text-gray-600 dark:text-gray-300">
                                                            <?php echo strtoupper(substr($cliente['nome_completo'], 0, 1)); ?>
                                                        </span>
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div>
                                                    <p class="font-medium text-gray-900 dark:text-white">
                                                        <?php echo htmlspecialchars($cliente['nome_completo']); ?>
                                                    </p>
                                                    <p class="text-sm text-gray-500 dark:text-gray-400">
                                                        <?php echo formatCPF($cliente['cpf']); ?>
                                                    </p>
                                                </div>
                                            </div>
                                        </td>
                                        <td>
                                            <span class="font-semibold">
                                                <?php echo $cliente['idade_no_aniversario']; ?> anos
                                            </span>
                                            <?php if ($isToday): ?>
                                                <span class="block text-xs text-yellow-600 dark:text-yellow-400">
                                                    🎂 Aniversário hoje!
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cliente['telefone1']): ?>
                                                <a href="<?php echo getWhatsAppLink($cliente['telefone1']); ?>" 
                                                   target="_blank"
                                                   class="text-green-600 hover:text-green-800 hover:underline">
                                                    <?php echo formatPhone($cliente['telefone1']); ?>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-gray-400">-</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($cliente['aceita_parabens']): ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-green-100 dark:bg-green-900 text-green-800 dark:text-green-200 text-xs rounded-full">
                                                    <i data-lucide="check" class="w-3 h-3"></i>
                                                    Sim
                                                </span>
                                            <?php else: ?>
                                                <span class="inline-flex items-center gap-1 px-2 py-1 bg-red-100 dark:bg-red-900 text-red-800 dark:text-red-200 text-xs rounded-full">
                                                    <i data-lucide="x" class="w-3 h-3"></i>
                                                    Não
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="flex items-center gap-2">
                                                <?php if ($cliente['telefone1'] && $cliente['aceita_parabens']): ?>
                                                    <a href="<?php echo getWhatsAppLink($cliente['telefone1'], 'Parabéns pelo seu aniversário! 🎉🎂 Desejamos um dia repleto de alegrias e realizações!'); ?>" 
                                                       target="_blank"
                                                       class="inline-flex items-center justify-center w-8 h-8 text-green-600 hover:text-green-800 hover:bg-green-100 dark:hover:bg-green-900 rounded-lg"
                                                       title="Enviar parabéns">
                                                        <i data-lucide="message-circle" class="w-4 h-4"></i>
                                                    </a>
                                                <?php endif; ?>
                                                
                                                <a href="cliente_editar.php?id=<?php echo $cliente['id']; ?>" 
                                                   class="inline-flex items-center justify-center w-8 h-8 text-blue-600 hover:text-blue-800 hover:bg-blue-100 dark:hover:bg-blue-900 rounded-lg"
                                                   title="Editar cliente">
                                                    <i data-lucide="edit" class="w-4 h-4"></i>
                                                </a>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    
                    <!-- Rodapé da tabela -->
                    <div class="px-6 py-4 border-t dark:border-gray-700 bg-gray-50 dark:bg-gray-700">
                        <div class="flex items-center justify-between">
                            <p class="text-sm text-gray-600 dark:text-gray-400">
                                Total: <?php echo $totalAniversariantes; ?> aniversariantes em <?php echo getMonthName($mes); ?>
                            </p>
                            <div class="text-sm text-gray-600 dark:text-gray-400">
                                <?php echo $totalAceitamParabens; ?> aceitam receber parabéns
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
            
        </main>
        
    </div>
    
</div>

<script>
// Função para enviar parabéns em massa
function sendBulkMessages() {
    const aniversariantes = <?php echo json_encode(array_filter($aniversariantes, function($c) { return $c['aceita_parabens'] && $c['telefone1']; })); ?>;
    
    if (aniversariantes.length === 0) {
        showToast('Nenhum aniversariante com telefone e que aceita parabéns encontrado.', 'warning');
        return;
    }
    
    if (confirm(`Deseja enviar mensagens de parabéns para ${aniversariantes.length} clientes?`)) {
        let count = 0;
        const message = 'Parabéns pelo seu aniversário! 🎉🎂 Desejamos um dia repleto de alegrias e realizações!';
        
        aniversariantes.forEach((cliente, index) => {
            setTimeout(() => {
                const whatsappUrl = `https://wa.me/55${cliente.telefone1}?text=${encodeURIComponent(message)}`;
                window.open(whatsappUrl, '_blank');
                count++;
                
                if (count === aniversariantes.length) {
                    showToast('Todas as mensagens foram abertas no WhatsApp!', 'success');
                }
            }, index * 1000); // Delay de 1 segundo entre cada mensagem
        });
    }
}

// Função para exportar lista de aniversariantes
function exportBirthdays() {
    const mes = <?php echo $mes; ?>;
    const ano = <?php echo $ano; ?>;
    const mesNome = '<?php echo getMonthName($mes); ?>';
    
    // Simula a exportação
    showToast(`Exportando aniversariantes de ${mesNome}/${ano}...`, 'info');
    
    // Aqui você implementaria a lógica real de exportação
    setTimeout(() => {
        showToast('Lista exportada com sucesso!', 'success');
    }, 2000);
}

// Auto-submit do formulário quando mudar mês/ano
document.getElementById('mes').addEventListener('change', function() {
    this.form.submit();
});

document.getElementById('ano').addEventListener('change', function() {
    this.form.submit();
});
</script>

<?php include 'templates/footer.php'; ?>
