CREATE DATABASE tunedrocks
  DEFAULT CHARACTER SET utf8
  DEFAULT COLLATE utf8_general_ci;

CREATE USER 'tunedrocks'@'localhost' IDENTIFIED BY 'tunedrocks';

GRANT ALL PRIVILEGES ON citysearch.* TO 'tunedrocks'@'localhost';

FLUSH PRIVILEGES;
