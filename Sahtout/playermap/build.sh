VERSION=$(cat VERSION)
docker build -t playermap:$VERSION -t playermap:latest .