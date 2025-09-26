<?php
// Template do Rodapé - Salvar como templates/footer.php
?>

<!-- Scripts principais do sistema -->
<script>
// Inicializa os ícones Lucide
lucide.createIcons();

// Funções globais do sistema
document.addEventListener('DOMContentLoaded', function() {
    
    // --- CONTROLE DA SIDEBAR ---
    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebar = document.getElementById('main-sidebar');
    
    if (sidebarToggle && sidebar) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('sidebar-mini');
            
            // Alterna os ícones do botão
            const leftIcon = sidebarToggle.querySelector('[data-lucide="chevrons-left"]');
            const rightIcon = sidebarToggle.querySelector('[data-lucide="chevrons-right"]');
            
            if (sidebar.classList.contains('sidebar-mini')) {
                leftIcon.classList.add('hidden');
                rightIcon.classList.remove('hidden');
            } else {
                leftIcon.classList.remove('hidden');
                rightIcon.classList.add('hidden');
            }
            
            // Salva o estado no servidor
            fetch('ajax_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: 'action=toggle_sidebar&collapsed=' + (sidebar.classList.contains('sidebar-mini') ? '1' : '0')
            });
        });
    }
    
    // --- MÁSCARAS DE INPUT ---
    
    // Máscara de CPF
    document.querySelectorAll('input[data-mask="cpf"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    });
    
    // Máscara de CNPJ
    document.querySelectorAll('input[data-mask="cnpj"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{2})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1.$2');
            value = value.replace(/(\d{3})(\d)/, '$1/$2');
            value = value.replace(/(\d{4})(\d{1,2})$/, '$1-$2');
            e.target.value = value;
        });
    });
    
    // Máscara de telefone
    document.querySelectorAll('input[data-mask="phone"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            
            // Adiciona +55 se não tiver
            if (value.length > 0 && !value.startsWith('55')) {
                value = '55' + value;
            }
            
            // Formata o telefone
            if (value.length <= 13) {
                value = value.replace(/(\d{2})(\d{2})(\d)/, '+$1 ($2) $3');
                value = value.replace(/(\d{4})(\d)/, '$1-$2');
                value = value.replace(/(\d{4})-(\d)(\d{4})/, '$1$2-$3');
            }
            
            e.target.value = value;
        });
    });
    
    // Máscara de CEP
    document.querySelectorAll('input[data-mask="cep"]').forEach(function(input) {
        input.addEventListener('input', function(e) {
            let value = e.target.value.replace(/\D/g, '');
            value = value.replace(/(\d{5})(\d)/, '$1-$2');
            e.target.value = value;
        });
    });
    
    // --- BUSCA DE CEP ---
    document.querySelectorAll('button[data-action="buscar-cep"]').forEach(function(button) {
        button.addEventListener('click', function() {
            const cepInput = document.querySelector('input[name="cep"]');
            if (!cepInput) return;
            
            const cep = cepInput.value.replace(/\D/g, '');
            if (cep.length !== 8) {
                alert('CEP deve ter 8 dígitos');
                return;
            }
            
            // Mostra loading
            button.innerHTML = '<div class="spinner"></div>';
            button.disabled = true;
            
            fetch(`https://viacep.com.br/ws/${cep}/json/`)
                .then(response => response.json())
                .then(data => {
                    if (data.erro) {
                        alert('CEP não encontrado');
                        return;
                    }
                    
                    // Preenche os campos
                    const ruaInput = document.querySelector('input[name="rua"]');
                    const bairroInput = document.querySelector('input[name="bairro"]');
                    const cidadeInput = document.querySelector('input[name="cidade"]');
                    const estadoInput = document.querySelector('select[name="estado"]');
                    
                    if (ruaInput) ruaInput.value = data.logradouro;
                    if (bairroInput) bairroInput.value = data.bairro;
                    if (cidadeInput) cidadeInput.value = data.localidade;
                    if (estadoInput) estadoInput.value = data.uf;
                })
                .catch(error => {
                    console.error('Erro ao buscar CEP:', error);
                    alert('Erro ao buscar CEP');
                })
                .finally(() => {
                    button.innerHTML = '<i data-lucide="search"></i>';
                    button.disabled = false;
                    lucide.createIcons();
                });
        });
    });
    
    // --- CONFIRMAÇÕES DE EXCLUSÃO ---
    document.querySelectorAll('button[data-action="delete"], a[data-action="delete"]').forEach(function(element) {
        element.addEventListener('click', function(e) {
            e.preventDefault();
            
            const itemName = this.getAttribute('data-item') || 'este item';
            const confirmMessage = `Tem certeza que deseja excluir ${itemName}?`;
            
            if (confirm(confirmMessage)) {
                // Se for um link, redireciona
                if (this.tagName === 'A') {
                    window.location.href = this.href;
                } else {
                    // Se for um botão, submete o formulário pai
                    const form = this.closest('form');
                    if (form) {
                        form.submit();
                    }
                }
            }
        });
    });
    
    // --- PREVIEW DE IMAGENS ---
    document.querySelectorAll('input[type="file"][data-preview]').forEach(function(input) {
        input.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const previewId = this.getAttribute('data-preview');
            const preview = document.getElementById(previewId);
            
            if (file && preview) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    preview.style.display = 'block';
                };
                reader.readAsDataURL(file);
            }
        });
    });
    
    // --- BUSCA EM TEMPO REAL ---
    document.querySelectorAll('input[data-search-table]').forEach(function(input) {
        let timeout;
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                const tableId = this.getAttribute('data-search-table');
                const table = document.getElementById(tableId);
                const searchTerm = this.value.toLowerCase();
                
                if (table) {
                    const rows = table.querySelectorAll('tbody tr');
                    rows.forEach(row => {
                        const text = row.textContent.toLowerCase();
                        row.style.display = text.includes(searchTerm) ? '' : 'none';
                    });
                }
            }, 300);
        });
    });
    
    // --- ORDENAÇÃO DE TABELAS ---
    document.querySelectorAll('th[data-sort]').forEach(function(th) {
        th.style.cursor = 'pointer';
        th.addEventListener('click', function() {
            const table = this.closest('table');
            const tbody = table.querySelector('tbody');
            const rows = Array.from(tbody.querySelectorAll('tr'));
            const column = this.getAttribute('data-sort');
            const isAsc = this.classList.contains('sort-asc');
            
            // Remove classes de ordenação de outros cabeçalhos
            table.querySelectorAll('th').forEach(header => {
                header.classList.remove('sort-asc', 'sort-desc');
            });
            
            // Adiciona classe de ordenação
            this.classList.add(isAsc ? 'sort-desc' : 'sort-asc');
            
            // Ordena as linhas
            rows.sort((a, b) => {
                const aValue = a.querySelector(`[data-value="${column}"]`)?.textContent || 
                              a.cells[parseInt(column)]?.textContent || '';
                const bValue = b.querySelector(`[data-value="${column}"]`)?.textContent || 
                              b.cells[parseInt(column)]?.textContent || '';
                
                if (isAsc) {
                    return bValue.localeCompare(aValue, 'pt-BR', { numeric: true });
                } else {
                    return aValue.localeCompare(bValue, 'pt-BR', { numeric: true });
                }
            });
            
            // Reinsere as linhas ordenadas
            rows.forEach(row => tbody.appendChild(row));
        });
    });
    
    // --- NOTIFICAÇÕES TOAST ---
    window.showToast = function(message, type = 'info') {
        const toast = document.createElement('div');
        toast.className = `fixed top-4 right-4 p-4 rounded-md shadow-lg z-50 transition-all duration-300 transform translate-x-full`;
        
        const colors = {
            success: 'bg-green-500 text-white',
            error: 'bg-red-500 text-white',
            warning: 'bg-yellow-500 text-white',
            info: 'bg-blue-500 text-white'
        };
        
        toast.className += ' ' + colors[type];
        toast.textContent = message;
        
        document.body.appendChild(toast);
        
        // Anima a entrada
        setTimeout(() => {
            toast.classList.remove('translate-x-full');
        }, 100);
        
        // Remove após 3 segundos
        setTimeout(() => {
            toast.classList.add('translate-x-full');
            setTimeout(() => {
                document.body.removeChild(toast);
            }, 300);
        }, 3000);
    };
    
});

// --- FUNÇÕES DE EXPORTAÇÃO ---

// Exportar para Excel
window.exportToExcel = function(tableId, filename = 'dados') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const wb = XLSX.utils.table_to_book(table);
    XLSX.writeFile(wb, filename + '.xlsx');
};

// Exportar para CSV
window.exportToCSV = function(tableId, filename = 'dados') {
    const table = document.getElementById(tableId);
    if (!table) return;
    
    const rows = table.querySelectorAll('tr');
    const csv = Array.from(rows).map(row => {
        const cells = row.querySelectorAll('th, td');
        return Array.from(cells).map(cell => {
            let text = cell.textContent.trim();
            // Escapa aspas duplas
            text = text.replace(/"/g, '""');
            // Envolve em aspas se contém vírgula, quebra de linha ou aspas
            if (text.includes(',') || text.includes('\n') || text.includes('"')) {
                text = `"${text}"`;
            }
            return text;
        }).join(',');
    }).join('\n');
    
    const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
};

// Exportar para PDF
window.exportToPDF = function(tableId, filename = 'dados') {
    const { jsPDF } = window.jspdf;
    const doc = new jsPDF();
    
    const table = document.getElementById(tableId);
    if (!table) return;
    
    // Configuração básica
    doc.setFontSize(10);
    
    // Adiciona título
    doc.setFontSize(16);
    doc.text(filename, 20, 20);
    
    // Adiciona data
    doc.setFontSize(10);
    doc.text('Data: ' + new Date().toLocaleDateString('pt-BR'), 20, 30);
    
    // Converte tabela para PDF (implementação básica)
    const rows = table.querySelectorAll('tr');
    let y = 50;
    
    Array.from(rows).forEach((row, index) => {
        const cells = row.querySelectorAll('th, td');
        let x = 20;
        
        Array.from(cells).forEach((cell, cellIndex) => {
            const text = cell.textContent.trim();
            if (text.length > 20) {
                doc.text(text.substring(0, 20) + '...', x, y);
            } else {
                doc.text(text, x, y);
            }
            x += 40;
        });
        
        y += 10;
        
        // Nova página se necessário
        if (y > 280) {
            doc.addPage();
            y = 20;
        }
    });
    
    doc.save(filename + '.pdf');
};
</script>

<!-- Estilos adicionais para elementos dinâmicos -->
<style>
/* Estilos para ordenação de tabelas */
th[data-sort]:hover {
    background-color: rgba(59, 130, 246, 0.1);
}

th.sort-asc::after {
    content: ' ↑';
    color: #3b82f6;
}

th.sort-desc::after {
    content: ' ↓';
    color: #3b82f6;
}

/* Animações suaves */
.fade-in {
    animation: fadeIn 0.3s ease-in-out;
}

@keyframes fadeIn {
    from { opacity: 0; transform: translateY(-10px); }
    to { opacity: 1; transform: translateY(0); }
}

/* Loading states */
.loading {
    opacity: 0.6;
    pointer-events: none;
}

/* Tooltips personalizados */
[title]:hover::after {
    content: attr(title);
    position: absolute;
    bottom: 100%;
    left: 50%;
    transform: translateX(-50%);
    background: rgba(0, 0, 0, 0.8);
    color: white;
    padding: 4px 8px;
    border-radius: 4px;
    font-size: 12px;
    white-space: nowrap;
    z-index: 1000;
}
</style>

</body>
</html>
