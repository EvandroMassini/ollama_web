# Segurança

Reporte vulnerabilidades de forma privada ao mantenedor, sem abrir uma issue pública inicialmente.

Esta aplicação foi projetada para LAN ou VPN confiável. Antes de exposição pública, adicione autenticação no Apache, HTTPS, limitação de requisições e segregação de rede. Não exponha a porta 11434 do Ollama à internet.

Nunca versione `config.local.php`. Use um usuário PostgreSQL exclusivo com permissão somente de leitura.
