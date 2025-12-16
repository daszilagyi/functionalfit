# Frontend Production Dockerfile - Multi-stage build
# Builds optimized static assets for production

# ============================================================================
# Build Stage - Compile React + Vite application
# ============================================================================
FROM node:20-alpine AS builder

# Set working directory
WORKDIR /app

# Install dependencies for build
RUN apk add --no-cache \
    python3 \
    make \
    g++ \
    git

# Copy package files
COPY package*.json ./

# Install dependencies
RUN npm ci --prefer-offline --no-audit --production=false

# Copy application source
COPY . .

# Build application with environment variables
ARG VITE_API_URL
ARG VITE_APP_NAME
ENV VITE_API_URL=${VITE_API_URL}
ENV VITE_APP_NAME=${VITE_APP_NAME}

# Build optimized production bundle
RUN npm run build

# ============================================================================
# Production Stage - Serve with Nginx
# ============================================================================
FROM nginx:1.25-alpine AS production

# Copy built assets from builder stage
COPY --from=builder /app/dist /usr/share/nginx/html

# Copy custom Nginx configuration for SPA
COPY infra/docker/frontend/nginx.conf /etc/nginx/conf.d/default.conf

# Add health check endpoint
RUN echo "OK" > /usr/share/nginx/html/health.txt

# Expose port 80
EXPOSE 80

# Health check
HEALTHCHECK --interval=30s --timeout=10s --start-period=5s --retries=3 \
    CMD wget --quiet --tries=1 --spider http://localhost/health.txt || exit 1

# Start Nginx
CMD ["nginx", "-g", "daemon off;"]
