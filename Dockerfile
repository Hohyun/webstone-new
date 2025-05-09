FROM devilbox/php-fpm:7.4-prod

RUN apt-get update -qq

RUN apt-get install -y ffmpeg
