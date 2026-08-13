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
//
// WHY DEPLOY_PATH IS A JOB PARAMETER, NOT A GLOBAL ENV VAR:
//    Jenkins global properties are shared by EVERY job on the controller. With more
//    than one Laravel app on this same server, a single global DEPLOY_PATH means
//    whichever job ran last silently decides where the other one deploys — and
//    rsync --delete would wipe a live app. HOST/USER/PORT stay global because they
//    are genuinely identical for all apps here; only the path (and the smoke-test
//    URL that follows from it) is per-app.
//
//    A .deploy-marker file on the server is checked before rsync runs. If the path
//    is ever wrong, the build stops before a single file is touched.
//
// Job setup: "Pipeline" job → "Pipeline script from SCM" → this repo,
// Script Path = Jenkinsfile.
//
// Server connection details come from Jenkins GLOBAL environment variables
// (Manage Jenkins → System → Global properties → Environment variables):
//   DEPLOY_HOST  (e.g. 153.92.x.x)
//   DEPLOY_USER  (e.g. u983422899)
//   DEPLOY_PORT  (Hostinger = 65002)
// DEPLOY_PATH must NOT be set globally — remove it if it is still there.
//
// One-time server setup for each app:
//   mkdir -p <DEPLOY_PATH>
//   echo "<app-name>" > <DEPLOY_PATH>/.deploy-marker
//   create <DEPLOY_PATH>/.env and <DEPLOY_PATH>/public/.htaccess by hand
//   (both are rsync-excluded and will never be shipped)
//
// Agent prerequisites (install once in WSL): node + npm, rsync, ssh/openssh-client.
// NOTE: the agent no longer needs php/composer — vendor is built on the server.
// Jenkins plugins: Git, Pipeline, SSH Agent.

pipeline {
    agent any

    parameters {
        // ── Per-app settings: THIS is what differs between jobs ──────────────
        // Absolute path on the server. Must already exist and contain .deploy-marker.
        // Example: /home/u983422899/domains/sibolang.net/public_html/monitoring
        string(name: 'DEPLOY_PATH',   defaultValue: '/home/u983422899/public_html/phpt', description: 'Absolute path of THIS app on the server. Per-job — never set globally.')
        // Contents of <DEPLOY_PATH>/.deploy-marker. Guards against deploying to the wrong app.
        // Example: monitoring-hakpakai
        string(name: 'DEPLOY_MARKER', defaultValue: 'APPNAME', description: 'Expected content of .deploy-marker in DEPLOY_PATH')
        // Public URL used by the smoke test.
        // Example: https://sibolang.net/monitoring
        string(name: 'SMOKE_URL',     defaultValue: 'https://monitoring.sibolang.net/public', description: 'Public URL to smoke test after release')

        // ── Shared toolchain settings ────────────────────────────────────────
        // alt-php 8.4 binary on the server. The default `php` CLI here is 7.4 —
        // do NOT use it. This must match the PHP version your .htaccess handler
        // pins for the web (php84___lsphp), so vendor and runtime agree.
        string(name: 'PHP_BIN',      defaultValue: '/opt/alt/php84/usr/bin/php', description: 'PHP CLI on the server — must match the web PHP (8.4)')
        string(name: 'COMPOSER_BIN', defaultValue: '/usr/local/bin/composer',    description: 'Composer on the server')
        string(name: 'SSH_CRED_ID',  defaultValue: 'hostinger-ssh',              description: 'Jenkins credentials ID — "SSH Username with private key"')
        booleanParam(name: 'RUN_MIGRATIONS',   defaultValue: true,  description: 'Run "artisan migrate --force" after deploy')
        booleanParam(name: 'MAINTENANCE_MODE', defaultValue: true,  description: 'Put the site in maintenance mode during the release step')
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
            // Fail fast with a clear message if the global env vars aren't set,
            // or if the per-job parameters are still on their placeholder defaults.
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

                    if (env.DEPLOY_PATH?.trim()) {
                        error("DEPLOY_PATH is set as a Jenkins GLOBAL env var. Remove it — " +
                              "it is shared by every job and will make one app deploy over another. " +
                              "The path belongs in this job's DEPLOY_PATH parameter.")
                    }

                    def placeholders = []
                    if (params.DEPLOY_PATH.contains('APPFOLDER') || params.DEPLOY_PATH.contains('USER')) { placeholders.add('DEPLOY_PATH') }
                    if (params.DEPLOY_MARKER == 'APPNAME')                                              { placeholders.add('DEPLOY_MARKER') }
                    if (params.SMOKE_URL.contains('DOMAIN'))                                            { placeholders.add('SMOKE_URL') }
                    if (placeholders) {
                        error("Still on placeholder defaults: ${placeholders.join(', ')}. " +
                              "Edit the defaults in this Jenkinsfile for this app.")
                    }

                    echo "Deploy target: ${env.DEPLOY_USER}@${env.DEPLOY_HOST}:${params.DEPLOY_PATH} (port ${env.DEPLOY_PORT})"
                    echo "Marker: ${params.DEPLOY_MARKER}   Smoke URL: ${params.SMOKE_URL}"
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

        stage('Guard: verify deploy path') {
            // Runs BEFORE rsync --delete. Confirms the target directory exists and
            // that its .deploy-marker names this app. A mistyped or stale path fails
            // here instead of overwriting a live application.
            steps {
                sshagent(credentials: [params.SSH_CRED_ID]) {
                    sh """
                        set -e
                        ssh ${SSH_OPTS} ${env.DEPLOY_USER}@${env.DEPLOY_HOST} bash -s <<'REMOTE'
set -e
MARKER_FILE='${params.DEPLOY_PATH}/.deploy-marker'
if [ ! -f "\$MARKER_FILE" ]; then
    echo "ABORT: \$MARKER_FILE not found."
    echo "Either DEPLOY_PATH is wrong, or this is a first deploy."
    echo "For a first deploy, run on the server:"
    echo "  mkdir -p '${params.DEPLOY_PATH}'"
    echo "  echo '${params.DEPLOY_MARKER}' > '${params.DEPLOY_PATH}/.deploy-marker'"
    exit 1
fi
FOUND=\$(head -1 "\$MARKER_FILE" | tr -d '[:space:]')
if [ "\$FOUND" != '${params.DEPLOY_MARKER}' ]; then
    echo "ABORT: marker mismatch. Expected '${params.DEPLOY_MARKER}' but found '\$FOUND'."
    echo "DEPLOY_PATH points at a DIFFERENT application. Refusing to deploy."
    exit 1
fi
echo "Marker OK: \$FOUND"
REMOTE
                    """
                }
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
                    //   /.deploy-marker    the guard file checked above
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
                            --exclude='/.deploy-marker' \
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

                    // Everything runs with PHP 8.4 on the server, so vendor/ is generated
                    // by the exact PHP that serves the web — no version mismatch possible.
                    // route:cache fails on closure routes (/, /logout), fall back to route:clear.
                    def remote = """
                        set -e
                        cd '${params.DEPLOY_PATH}'
                        ${down}
                        ${php} ${composer} install --no-dev --optimize-autoloader --no-interaction --prefer-dist --no-progress
                        ${migrate}
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
                sh """
                    set -e
                    code=\$(curl -s -o /dev/null -w '%{http_code}' '${params.SMOKE_URL}' || echo "000")
                    echo "HTTP status: \$code"
                    case "\$code" in
                        200|302) echo "OK" ;;
                        *) echo "Unexpected status \$code"; exit 1 ;;
                    esac
                """
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