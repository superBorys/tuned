docker run --name tuned.rocks -v /Users/ivan/Projects/tuned.rocks/:/app -p 80:80 webdevops/php-apache:debian-8-php7
docker run --name tuned.rocks-mysql -p 3306:3306 -v /Users/ivan/Projects/tuned.rocks/mysql/conf/:/etc/mysql/conf.d -v /Users/ivan/Projects/tuned.rocks/mysql/data/:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=qwerty -d mysql

CREATE DATABASE tuned_rocks
  DEFAULT CHARACTER SET utf8
  DEFAULT COLLATE utf8_general_ci;

CREATE USER 'tuned_rocks'@'%' IDENTIFIED BY 'CNf6rAibWctm';
GRANT ALL PRIVILEGES ON tuned_rocks.* TO 'tuned_rocks'@'%';
FLUSH PRIVILEGES;

docker run --name tuned.rocks-mysql -p 3306:3306 -v /Users/ivan/Projects/tuned.rocks/mysql/conf/:/etc/mysql/conf.d -v /Users/ivan/Projects/tuned.rocks/mysql/data/:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=qwerty -d mysql

docker start -e MYSQL_ROOT_PASSWORD=qwerty -d streetegg-mysql

docker run --name streetegg-mysql2 -p 3306:3306 -v /Users/ivan/Projects/real-estate-website/mysql/conf/:/etc/mysql/conf.d -v /Users/ivan/Projects/real-estate-website/mysql/data/:/var/lib/mysql -e MYSQL_ROOT_PASSWORD=qwerty -d mysql

docker run --name php7-with-xdebug webdevops/php-apache:debian-8-php7
