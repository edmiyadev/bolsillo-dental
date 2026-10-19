# ================================
# Bolsillo Dental Dev Aliases
# ================================
# Estándar de alias para trabajar con el entorno Dockerizado de Bolsillo Dental.
#
# ▸ CARGA MANUAL (por sesión):
#     source ./dev-aliases.sh
#
# ▸ CARGA AUTOMÁTICA (una sola vez, en ~/.bashrc o ~/.zshrc):
#     echo '[ -f "'"$PWD"'/dev-aliases.sh" ] && source "'"$PWD"'/dev-aliases.sh"' >> ~/.zshrc
#
# Nota: se usa `source`, NO ejecutar como script.

# Compose dev global y gestión de contenedores
alias bd="docker compose -f compose.dev.yaml"
alias bd-up="docker compose -f compose.dev.yaml up -d"
alias bd-up-build="docker compose -f compose.dev.yaml up -d --build"
alias bd-down="docker compose -f compose.dev.yaml down"
alias bd-logs="docker compose -f compose.dev.yaml logs -f"
alias bd-restart="docker compose -f compose.dev.yaml restart"

# Acceso y ejecución dentro del contenedor app (PHP 8.4)
alias bd-bash="docker compose -f compose.dev.yaml exec app bash"
alias bd-php="docker compose -f compose.dev.yaml exec app php"
alias bd-artisan="docker compose -f compose.dev.yaml exec app php artisan"
alias bd-pa="docker compose -f compose.dev.yaml exec app php artisan"
alias bd-composer="docker compose -f compose.dev.yaml exec app composer"

# Comandos Artisan frecuentes
alias bd-key="docker compose -f compose.dev.yaml exec app php artisan key:generate"
alias bd-migrate="docker compose -f compose.dev.yaml exec app php artisan migrate"
alias bd-seed="docker compose -f compose.dev.yaml exec app php artisan db:seed"
alias bd-tinker="docker compose -f compose.dev.yaml exec app php artisan tinker"
alias bd-test="docker compose -f compose.dev.yaml exec app php artisan test"
