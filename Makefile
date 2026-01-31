RED = \033[0;31m
GREEN = \033[0;32m
YELLOW = \033[0;33m
NC = \033[0m

.PHONY: up down stop start restart wait-db show-urls day-end day-start app artisan composer shell env-docker

up:
	@echo -e '$(GREEN)=> Iniciando contenedores Docker$(NC)'
	@if [ ! -f .env ]; then cp .env.docker .env && echo -e '$(GREEN)=> .env creado desde .env.docker$(NC)'; fi
	@if [ -f .env.docker ] && [ -f .env ]; then echo -e '$(YELLOW)=> Sugerencia: make env-docker para usar config Docker$(NC)'; fi
	docker compose up -d --build
	@$(MAKE) wait-db
	@$(MAKE) show-urls

down:
	@echo -e '$(YELLOW)=> Deteniendo contenedores (volúmenes se conservan)$(NC)'
	docker compose down

stop:
	@echo -e '$(YELLOW)=> Deteniendo contenedores (ideal para fin del día)$(NC)'
	docker compose stop
	@echo -e '$(GREEN)=> Mañana: make start$(NC)'

start:
	@echo -e '$(GREEN)=> Iniciando contenedores existentes$(NC)'
	docker compose start
	@$(MAKE) wait-db
	@$(MAKE) show-urls

wait-db:
	@echo -e '$(YELLOW)=> Esperando a MySQL...$(NC)'
	@sleep 5
	@until docker compose exec -T db mysqladmin ping -h localhost -u root -proot --silent 2>/dev/null; do \
		echo "  Esperando MySQL..."; sleep 2; \
	done
	@echo -e '$(GREEN)=> MySQL listo$(NC)'

show-urls:
	@echo ''; echo -e '$(GREEN)=> Acceso:$(NC)'; \
	echo -e '   API:  http://localhost:8000'; \
	echo -e '   API:  http://localhost:8000/docs/api'; \
	echo ''; echo -e '$(GREEN)=> Base de datos:$(NC)'; \
	echo -e '   Host: localhost:3306'; \
	echo -e '   User: root / root'; \
	echo -e '   DB:   carmot_api'; echo ''

day-end: stop
day-start: start

app:
	@echo -e '$(GREEN)=> Accediendo al contenedor de la aplicación$(NC)'
	docker compose exec app bash

artisan:
	docker compose exec app php artisan $(filter-out $@,$(MAKECMDGOALS))

composer:
	docker compose exec app composer $(filter-out $@,$(MAKECMDGOALS))

shell:
	@echo -e '$(GREEN)=> Accediendo al contenedor de la aplicación$(NC)'
	docker compose exec app bash

env-docker:
	@echo -e '$(GREEN)=> Cambiando a configuración Docker$(NC)'
	@if [ -f .env.docker ]; then \
		cp .env.docker .env; \
		echo -e '$(GREEN)=> .env actualizado desde .env.docker$(NC)'; \
	else \
		echo -e '$(RED)=> Error: .env.docker no existe$(NC)'; \
	fi

%:
	@:
