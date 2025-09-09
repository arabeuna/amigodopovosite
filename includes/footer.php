</main>
    
    <!-- Footer -->
    <footer class="bg-white border-t border-gray-200 mt-8">
        <div class="max-w-7xl mx-auto py-6 px-4">
            <div class="flex justify-between items-center">
                <div class="text-sm text-gray-600">
                    <p>&copy; <?= date('Y') ?> Associação Amigo do Povo. Todos os direitos reservados.</p>
                </div>
                <div class="text-sm text-gray-500">
                    <p>Sistema de Gerenciamento v1.0</p>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Scripts -->
    <script>
        // Função para fechar alertas
        function closeAlert(element) {
            element.parentElement.style.display = 'none';
        }
        
        // Auto-hide alerts após 5 segundos
        document.addEventListener('DOMContentLoaded', function() {
            const alerts = document.querySelectorAll('.alert-auto-hide');
            alerts.forEach(function(alert) {
                setTimeout(function() {
                    alert.style.opacity = '0';
                    setTimeout(function() {
                        alert.style.display = 'none';
                    }, 300);
                }, 5000);
            });
        });
        
        // Confirmação para ações perigosas
        function confirmAction(message) {
            return confirm(message || 'Tem certeza que deseja realizar esta ação?');
        }
        
        // Função para mostrar/ocultar senha
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const icon = input.nextElementSibling.querySelector('i');
            
            if (input.type === 'password') {
                input.type = 'text';
                icon.classList.remove('fa-eye');
                icon.classList.add('fa-eye-slash');
            } else {
                input.type = 'password';
                icon.classList.remove('fa-eye-slash');
                icon.classList.add('fa-eye');
            }
        }
    </script>
</body>
</html>