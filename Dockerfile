FROM php:8.1-fpm

RUN apt-get update && apt-get install -y \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    zip \
    unzip \
    nginx \
    python3 \
    python3-pip \
    python3-dev \
    python3-venv \
    cmake \
    build-essential \
    libopenblas-dev \
    liblapack-dev \
    libx11-dev \
    libgtk-3-dev \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgomp1 \
    libatlas-base-dev \
    && rm -rf /var/lib/apt/lists/*

# Install PHP extensions
RUN docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd

# Install Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Set working directory
WORKDIR /var/www/html

# Create Python virtual environment
RUN python3 -m venv /opt/venv

# Copy requirements.txt and install Python packages in virtual environment
COPY requirements.txt .
RUN /opt/venv/bin/pip install --no-cache-dir -r requirements.txt

# Copy PHP files
COPY . .

# Copy nginx config for PHP
COPY nginx-php.conf /etc/nginx/sites-available/default

# Create uploads directory and set permissions
RUN mkdir -p /var/www/html/uploads && \
    mkdir -p /var/www/html/scripts && \
    chown -R www-data:www-data /var/www/html && \
    chmod -R 755 /var/www/html

# Make Python script executable
RUN chmod +x /var/www/html/scripts/face_embedding.py

# Start services
COPY start.sh /start.sh
RUN chmod +x /start.sh

EXPOSE 80
CMD ["/start.sh"]