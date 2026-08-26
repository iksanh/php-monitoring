// CI/CD pipeline: build frontend assets on the Jenkins (WSL) agent, sync the app
// to Hostinger shared hosting over SSH, then build composer vendor + run release
// steps ON THE SERVER using the exact PHP that serves the web (8.4 via .htaccess).
//
// WHY THIS LAYOUT (lessons from the 500s we debugged):
//  • vendor/ is built ON THE SERVER with PHP 8.4 — never shipped from the agent.
//    The agent's PHP differed from the server's, which produced a vendor/ locked to
//    symfony 8.x / a brick/math layout the server couldn't load → 500s. Building
//    vendor with the same PHP that runs the web makes mismatches impossible.
//  • public/.htaccess is EXCLUDED from rsync. It carries the LiteSpeed PHP-8.4
//    handler line (AddHandler ...php84___lsphp .php). Without the exclude, rsync
//    --delete wiped it and the domain fell back to global PHP 7.4 → 500.
//  • PHP_BIN defaults to the alt-php 8.4 binary, NOT `php` (which is 7.4 CLI here).
//    All artisan/composer on the server go through this.
//  • DEPLOY_PATH is a JOB PARAMETER, not a global env var. Host/user/port are the
//    same for every app on this server, but the path is not — a single global
//    DEPLOY_PATH means whichever job ran last decides where the other one deploys,
//    and rsync --delete would wipe a live app. The global DEPLOY_PATH may still
//    exist for older jobs; this job ignores it.
//
// DATA AWAL DI SERVER (stage rilis):
//  • ProduksiSeeder jalan tiap deploy. Idempoten — jenis instansi, instansi
//    nyata, dan akun admin ditulis lewat kunci alami, akun yang sudah ada
//    dilewati. Password admin tercetak sekali di log build saat akun dibuat.
//  • Daftar aset tanah pemda diimpor dari berkas .xlsx yang diunggah SEKALI ke
//    storage/app/ di server (lihat parameter DAFTAR_ASET). Berkasnya tidak ada
//    di repositori dengan sengaja, dan harus berada di storage/ karena itu
//    satu-satunya folder data yang dikecualikan dari rsync --delete.
//
// Job setup: "Pipeline" job → "Pipeline script from SCM" → this repo,
// Script Path = Jenkinsfile.
//
// Server connection details come from Jenkins GLOBAL environment variables
// (Manage Jenkins → System → Global properties → Environment variables):
//   DEPLOY_HOST  (e.g. 153.92.x.x)
//   DEPLOY_USER  (e.g. u983422899)
//   DEPLOY_PORT  (Hostinger = 65002)
// The deploy path is set per job in the DEPLOY_PATH parameter below.
//
// Agent prerequisites (install once in WSL): node + npm, rsync, ssh/openssh-client.
// NOTE: the agent no longer needs php/composer — vendor is built on the server.
// Jenkins plugins: Git, Pipeline, SSH Agent.

pipeline {
    agent any

    parameters {
        // Absolute path of THIS app on the server. Per job — never set globally.
        string(name: 'DEPLOY_PATH', defaultValue: '/home/u983422899/public_html/monitoring', description: 'Absolute path of this app on the server')
        // alt-php 8.4 binary on the server. The default `php` CLI here is 7.4 —
        // do NOT use it. This must match the PHP version your .htaccess handler
        // pins for the web (php84___lsphp), so vendor and runtime agree.
        string(name: 'PHP_BIN',     defaultValue: '/opt/alt/php84/usr/bin/php', description: 'PHP CLI on the server — must match the web PHP (8.4). e.g. /opt/alt/php84/usr/bin/php')
        string(name: 'COMPOSER_BIN', defaultValue: '/usr/local/bin/composer', description: 'Composer on the server')
        string(name: 'SSH_CRED_ID', defaultValue: 'hostinger-ssh', description: 'Jenkins credentials ID — "SSH Username with private key"')
        booleanParam(name: 'RUN_MIGRATIONS',   defaultValue: true,  description: 'Run "artisan migrate --force" after deploy')
        booleanParam(name: 'MAINTENANCE_MODE', defaultValue: true,  description: 'Put the site in maintenance mode during the release step')
        // ProduksiSeeder hanya menulis lewat kunci alami dan tidak pernah menimpa
        // password akun yang sudah ada, jadi aman diulang tiap deploy. Password
        // administrator tercetak SEKALI di log build ini, saat akunnya dibuat.
        booleanParam(name: 'RUN_SEEDER',       defaultValue: true,  description: 'Jalankan ProduksiSeeder (jenis instansi, instansi nyata, akun admin). Idempoten.')
        // Daftar aset pemda sengaja tidak masuk repositori (lihat .gitignore),
        // jadi berkasnya diunggah SEKALI ke server dengan scp:
        //   scp -P 65002 "docs/Daftar Aset berupa Tanah Pemda THN 2025.xlsx" \
        //       u983422899@HOST:/home/u983422899/public_html/monitoring/storage/app/daftar-aset.xlsx
        // Letaknya di storage/ bukan docs/ karena rsync --delete di bawah
        // mengecualikan /storage — berkas di docs/ akan terhapus tiap deploy.
        // Path relatif terhadap DEPLOY_PATH. Kosongkan untuk melewati impor.
        string(name: 'DAFTAR_ASET', defaultValue: 'storage/app/daftar-aset.xlsx', description: 'Berkas daftar aset pemda di server, relatif ke DEPLOY_PATH. Kosongkan untuk melewati impor.')
    }

    options {
        timestamps()
        disableConcurrentBuilds()
        buildDiscarder(logRotator(numToKeepStr: '10'))
    }

    environment {
        // accept-new = trust host key first time, then pin it. Port pinned to Hostinger's 65002.
        SSH_OPTS = "-o StrictHostKeyChecking=accept-new -p ${env.DEPLOY_PORT}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout scm
            }
        }

        stage('Verify config') {
            // Fail fast with a clear message if the global env vars aren't set.
            steps {
                script {
                    def missing = []
                    if (!env.DEPLOY_HOST?.trim()) { missing.add('DEPLOY_HOST') }
                    if (!env.DEPLOY_USER?.trim()) { missing.add('DEPLOY_USER') }
                    if (!env.DEPLOY_PORT?.trim()) { missing.add('DEPLOY_PORT') }
                    if (missing) {
                        error("Missing Jenkins global env vars: ${missing.join(', ')}. " +
                              "Set them in Manage Jenkins → System → Global properties → Environment variables.")
                    }
                    echo "Deploy target: ${env.DEPLOY_USER}@${env.DEPLOY_HOST}:${params.DEPLOY_PATH} (port ${env.DEPLOY_PORT})"
                }
            }
        }

        stage('Verify agent tooling') {
            // Agent only needs node/npm + rsync/ssh now. No php/composer here.
            steps {
                sh '''
                    set -e
                    echo "Node:  $(node -v)"
                    echo "npm:   $(npm -v)"
                    echo "rsync: $(rsync --version | head -1)"
                '''
            }
        }

        stage('Build: composer (agent — untuk aset saja)') {
            // Flux/Livewire menaruh CSS-nya di vendor/, dan resources/css/app.css
            // mengimpornya, jadi Vite di agent perlu vendor/ agar import itu resolve.
            //
            // --no-autoloader mematikan post-autoload-dump (package:discover), yang
            // butuh bootstrap/cache dan .env — keduanya tidak ada di workspace Jenkins.
            // Kita hanya perlu file paketnya di disk, bukan aplikasi yang bootable.
            //
            // vendor/ hasil agent TIDAK dikirim — sudah di-exclude dari rsync, dan
            // vendor produksi tetap dibangun di server dengan PHP 8.4.
            steps {
                sh '''
                    set -e
                    mkdir -p bootstrap/cache storage/framework/{cache,sessions,views} storage/logs
                    composer install --no-scripts --no-autoloader --no-interaction --prefer-dist --no-progress
                '''
            }
        }

        stage('Build: Frontend assets') {
            steps {
                sh '''
                    set -e
                    npm ci
                    npm run build
                '''
            }
        }

        stage('Deploy: rsync to server') {
            steps {
                sshagent(credentials: [params.SSH_CRED_ID]) {
                    // --delete keeps the server in sync with the build. The excludes are
                    // NEVER touched on the server:
                    //   /.env              real production env
                    //   /storage           runtime storage (logs, sessions, cache)
                    //   /public/storage    storage symlink
                    //   /public/.htaccess  carries the PHP 8.4 LiteSpeed handler — DO NOT overwrite
                    //   /vendor            built on the server in the next stage, not shipped
                    sh """
                        set -e
                        rsync -az --delete \
                            --exclude='.git' \
                            --exclude='.github' \
                            --exclude='node_modules' \
                            --exclude='tests' \
                            --exclude='/.env' \
                            --exclude='/storage' \
                            --exclude='/vendor' \
                            --exclude='/public/storage' \
                            --exclude='/public/.htaccess' \
                            --exclude='/public/hot' \
                            --exclude='Jenkinsfile' \
                            -e "ssh ${SSH_OPTS}" \
                            ./ ${env.DEPLOY_USER}@${env.DEPLOY_HOST}:'${params.DEPLOY_PATH}/'
                    """
                }
            }
        }

        stage('Release: composer, migrate & cache (on server)') {
            steps {
                script {
                    def php     = params.PHP_BIN
                    def composer= params.COMPOSER_BIN
                    def down    = params.MAINTENANCE_MODE ? "${php} artisan down --retry=15 || true" : "true"
                    def migrate = params.RUN_MIGRATIONS   ? "${php} artisan migrate --force"          : "echo 'migrations skipped'"
                    def seed    = params.RUN_SEEDER       ? "${php} artisan db:seed --class=ProduksiSeeder --force" : "echo 'seeder dilewati'"

                    // Impor daftar aset: aman diulang — bidang dikenali dari
                    // nomor_urut (kode barang + register) dan yang sudah ada
                    // dilewati, jadi deploy berikutnya tidak menggandakan data.
                    // Tanpa --perbarui, supaya tanggal dan penanda diproses yang
                    // dicatat operator tidak tertimpa berkas pemda yang lebih tua.
                    // memory_limit dinaikkan: berkasnya seribu baris lebih dan
                    // CLI shared hosting kerap dibatasi 128M.
                    def berkas  = params.DAFTAR_ASET?.trim()
                    def impor   = berkas ? """
                        if [ -f '${berkas}' ]; then
                            ${php} -d memory_limit=512M artisan impor:daftar-aset '${berkas}'
                        else
                            echo 'Daftar aset tidak ada di ${berkas} — impor dilewati. Unggah dengan scp bila memang diperlukan.'
                        fi
                    """.stripIndent().trim() : "echo 'impor daftar aset dilewati'"

                    // Everything runs with PHP 8.4 on the server, so vendor/ is generated
                    // by the exact PHP that serves the web — no version mismatch possible.
                    // route:cache fails on closure routes (/, /logout), fall back to route:clear.
                    def remote = """
                        set -e
                        cd '${params.DEPLOY_PATH}'
                        ${down}
                        ${php} ${composer} install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
                        ${migrate}
                        ${seed}
                        ${impor}
                        ${php} artisan storage:link || true
                        ${php} artisan config:clear
                        ${php} artisan view:clear
                        ${php} artisan config:cache
                        ${php} artisan view:cache
                        ${php} artisan route:cache || ${php} artisan route:clear
                        ${php} artisan up || true
                        echo 'Release complete.'
                    """.stripIndent()

                    writeFile file: 'deploy_remote.sh', text: remote
                    sshagent(credentials: [params.SSH_CRED_ID]) {
                        sh "ssh ${SSH_OPTS} ${env.DEPLOY_USER}@${env.DEPLOY_HOST} 'bash -ls' < deploy_remote.sh"
                    }
                }
            }
        }

        stage('Smoke test') {
            // Confirm the site actually responds instead of declaring success blindly.
            steps {
                sh '''
                    set -e
                    code=$(curl -s -o /dev/null -w '%{http_code}' https://monitoring.sibolang.net || echo "000")
                    echo "HTTP status: $code"
                    case "$code" in
                        200|302) echo "OK" ;;
                        *) echo "Unexpected status $code"; exit 1 ;;
                    esac
                '''
            }
        }
    }

    post {
        success {
            echo "✅ Deployed to ${env.DEPLOY_USER}@${env.DEPLOY_HOST}:${params.DEPLOY_PATH} (port ${env.DEPLOY_PORT})"
        }
        failure {
            // Best-effort: bring the site back up if a failed release left it down.
            script {
                if (env.DEPLOY_HOST?.trim()) {
                    sshagent(credentials: [params.SSH_CRED_ID]) {
                        sh """
                            ssh ${SSH_OPTS} ${env.DEPLOY_USER}@${env.DEPLOY_HOST} \
                                "cd '${params.DEPLOY_PATH}' && ${params.PHP_BIN} artisan up || true" || true
                        """
                    }
                }
            }
            echo "❌ Build failed — site brought back up if it was in maintenance mode."
        }
        always {
            sh 'rm -f deploy_remote.sh || true'
        }
    }
}