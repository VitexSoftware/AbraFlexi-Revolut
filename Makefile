all:

buildimage:
	docker build -f Containerfile  -t vitexsoftware/abraflexi-revolut:latest .

buildx:
	docker buildx build  -f Containerfile  . --push --platform linux/arm/v7,linux/arm64/v8,linux/amd64 --tag vitexsoftware/abraflexi-revolut:latest

drun:
	docker run  -f Containerfile --env-file .env vitexsoftware/abraflexi-revolut:latest
