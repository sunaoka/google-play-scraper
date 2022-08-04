#!/bin/sh

resources_dir="$(cd "$(dirname "$0")" && pwd)/../resources"

curl -sS 'https://play.google.com/store/apps/details?id=com.mojang.minecraftpe&hl=en&gl=us' -o "${resources_dir}/app1.html"

curl -sS 'https://play.google.com/store/apps/details?id=com.instagram.android&hl=zh&gl=cn' -o "${resources_dir}/app2.html"

curl -sS 'https://play.google.com/store/apps?hl=en&gl=us' -o "${resources_dir}/categories.html"

curl -sS 'https://play.google.com/store/search?q=unicorns&c=apps&hl=en&gl=us&price=1&rating=1' -o "${resources_dir}/search.html"
