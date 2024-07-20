mkdir -p vendor/Robot32lib/ImageSource
cd vendor/Robot32lib/ImageSource

curl "https://raw.githubusercontent.com/mlaak/robot32lib/main/vendor/Robot32lib/ImageSource/ImageSource.php" > ImageSource.php
curl "https://raw.githubusercontent.com/mlaak/robot32lib/main/vendor/Robot32lib/ImageSource/pictures.archive" > pictures.archive
curl "https://raw.githubusercontent.com/mlaak/robot32lib/main/vendor/Robot32lib/ImageSource/pictures.locations.txt" > pictures.locations.txt

cd ..
cd ..
cd ..

