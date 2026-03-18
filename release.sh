#!/usr/bin/env bash

# Проверка, указана ли версия
if [ -z "$1" ]; then
    echo "Использование: ./release.sh <версия>"
    echo "Пример: ./release.sh v1.0.0"
    exit 1
fi

VERSION=$1

# Убедимся, что версия начинается с 'v' (необязательно, но рекомендуется)
if [[ ! $VERSION =~ ^v ]]; then
    VERSION="v$VERSION"
fi

echo "Выпуск версии $VERSION..."

# Проверка на наличие незафиксированных изменений
if ! git rev-parse --verify HEAD >/dev/null 2>&1; then
    echo "Ошибка: В репозитории отсутствуют коммиты. Пожалуйста, сделайте хотя бы один коммит перед релизом."
    exit 1
fi

if ! git diff-index --quiet HEAD --; then
    echo "Ошибка: У вас есть незафиксированные изменения. Пожалуйста, закоммитьте или спрячьте (stash) их."
    exit 1
fi

# Убедимся, что мы находимся на ветке main или master
BRANCH=$(git rev-parse --abbrev-ref HEAD)
if [[ "$BRANCH" != "main" && "$BRANCH" != "master" ]]; then
    echo "Предупреждение: Вы находитесь не в ветке main или master. Продолжить? (y/n)"
    read -r response
    if [[ ! $response =~ ^[Yy]$ ]]; then
        exit 1
    fi
fi

# Отправка текущей ветки в репозиторий
echo "Отправка $BRANCH в origin..."
git push origin "$BRANCH"

# Создание тега
echo "Создание тега $VERSION..."
git tag -a "$VERSION" -m "Release $VERSION"

# Отправка тега в репозиторий
echo "Отправка тега $VERSION в origin..."
git push origin "$VERSION"

echo "Версия $VERSION успешно выпущена!"
echo "Packagist обновит данные автоматически, если настроен вебхук."
