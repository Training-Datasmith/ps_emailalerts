#!/usr/bin/env bash
set -euo pipefail

ROOT="$(cd "$(dirname "$0")/../.." && pwd)"
PHAR="/tmp/phpunit-7.5.20.phar"
EXPECTED_SHA="472b6e275e4479bab71f2f7744d2e22ace9566a4e3cef37952e4d9c07f6d3a92"
EXPECTED_FP="D8406D0D82947747293778314AA394086372C20A"
EXPECTED_PHPUNIT_TESTS=34
RESULTS="/opt/cursor/artifacts/suite-results.md"
DOCKER="docker"
if ! docker info >/dev/null 2>&1; then
  DOCKER="sudo docker"
fi
NETWORK="ps-emailalerts-net"
DB_NAME="ps-emailalerts-db"
PHP_IMAGE="ps-emailalerts-php"
PHP_DIGEST="sha256:1e4671c8e26df6ae1d99b0e0059cdaa2fe72351c20c136e787dc463a165104e5"
MYSQL_DIGEST="sha256:dab0a802b44617303694fb17d166501de279c3031ddeb28c56ecf7fcab5ef0da"

mapfile -t EXPECTED_TEST_FILES < <(git -C "${ROOT}" ls-files 'tests' | grep 'Test\.php$' | sort)
EXPECTED_FILE_COUNT="${#EXPECTED_TEST_FILES[@]}"
if [ "${EXPECTED_FILE_COUNT}" -lt 1 ]; then
  echo "No test files listed in git index under tests/" >&2
  exit 1
fi
for rel in "${EXPECTED_TEST_FILES[@]}"; do
  if [ ! -f "${ROOT}/${rel}" ]; then
    echo "Missing test file on disk: ${rel}" >&2
    exit 1
  fi
done

mkdir -p /opt/cursor/artifacts

curl -fsSL -o "${PHAR}.asc" https://phar.phpunit.de/phpunit-7.5.20.phar.asc
curl -fsSL -o "${PHAR}" https://phar.phpunit.de/phpunit-7.5.20.phar
echo "${EXPECTED_SHA}  ${PHAR}" | sha256sum -c -

if ! gpg --list-keys "${EXPECTED_FP}" >/dev/null 2>&1; then
  curl -fsSL https://sebastian-bergmann.de/gpg.asc | gpg --import --batch
fi
VERIFY_OUT="$(gpg --status-fd 1 --verify "${PHAR}.asc" "${PHAR}" 2>&1)"
echo "${VERIFY_OUT}" | grep -q "VALIDSIG"
FP_LINE="$(echo "${VERIFY_OUT}" | grep '^\[GNUPG:\] VALIDSIG' | tail -1)"
echo "${FP_LINE}" | grep -q "${EXPECTED_FP}"

${DOCKER} network inspect "${NETWORK}" >/dev/null 2>&1 || ${DOCKER} network create "${NETWORK}"
if ${DOCKER} ps -a --format '{{.Names}}' | grep -qx "${DB_NAME}"; then
  if ! ${DOCKER} ps --format '{{.Names}}' | grep -qx "${DB_NAME}"; then
    ${DOCKER} start "${DB_NAME}" >/dev/null
  fi
else
  ${DOCKER} run -d --name "${DB_NAME}" --network "${NETWORK}" \
    -e MYSQL_ROOT_PASSWORD=root -e MYSQL_DATABASE=ps_emailalerts \
    "mysql@${MYSQL_DIGEST}" \
    --character-set-server=utf8 --collation-server=utf8_general_ci
fi

for i in $(seq 1 60); do
  if ${DOCKER} exec "${DB_NAME}" mysqladmin ping -h127.0.0.1 -uroot -proot --silent 2>/dev/null; then
    break
  fi
  sleep 1
done
${DOCKER} exec "${DB_NAME}" mysqladmin ping -h127.0.0.1 -uroot -proot --silent

${DOCKER} build --network=host -t "${PHP_IMAGE}" -f "${ROOT}/tests/docker/php-7.1.33/Dockerfile" "${ROOT}"

RUN_PHPUNIT() {
  local extra="$1"
  ${DOCKER} run --rm --network "container:${DB_NAME}" \
    -e DB_HOST=127.0.0.1 -e DB_PORT=3306 \
    -e DB_NAME=ps_emailalerts -e DB_USER=root -e DB_PASSWORD=root \
    -v "${ROOT}":/work -w /work/tests \
    -v "${PHAR}":/usr/local/bin/phpunit:ro \
    --entrypoint php \
    "${PHP_IMAGE}" \
    /usr/local/bin/phpunit --configuration phpunit.xml.dist ${extra}
}

LOG="$(mktemp)"
set +e
RUN_PHPUNIT "" | tee -a "${LOG}"
RC1=${PIPESTATUS[0]}
RUN_PHPUNIT "" | tee -a "${LOG}"
RC2=${PIPESTATUS[0]}
RUN_PHPUNIT "--order-by=random --random-order-seed=20261008" | tee -a "${LOG}"
RC3=${PIPESTATUS[0]}
RUN_PHPUNIT "--order-by=random --random-order-seed=20261008" | tee -a "${LOG}"
RC4=${PIPESTATUS[0]}
set -e

FAIL=0
for rc in "$RC1" "$RC2" "$RC3" "$RC4"; do
  if [ "$rc" -ne 0 ]; then
    FAIL=1
  fi
done

if grep -qE '^(FAILURES!|There were [0-9]+ failures)' "${LOG}"; then
  FAIL=1
fi
if grep -qE '^(ERRORS!|There were [0-9]+ errors)' "${LOG}"; then
  FAIL=1
fi

OK_COUNT=0
while IFS= read -r line; do
  OK_COUNT=$((OK_COUNT + 1))
  run_tests="$(echo "${line}" | sed -n 's/^OK (\([0-9]*\) tests.*/\1/p')"
  if [ -z "${run_tests}" ] || [ "${run_tests}" -ne "${EXPECTED_PHPUNIT_TESTS}" ]; then
    echo "Unexpected test count in summary: ${line}" >&2
    FAIL=1
  fi
done < <(grep -E '^OK \(' "${LOG}" || true)

if [ "${OK_COUNT}" -ne 4 ]; then
  echo "Expected 4 OK summaries, found ${OK_COUNT}" >&2
  FAIL=1
fi

DISCOVERED_COUNT="$(find "${ROOT}/tests" -name '*Test.php' | wc -l | tr -d ' ')"
if [ "${DISCOVERED_COUNT}" -ne "${EXPECTED_FILE_COUNT}" ]; then
  echo "On-disk test file count ${DISCOVERED_COUNT} != git index ${EXPECTED_FILE_COUNT}" >&2
  FAIL=1
fi
mapfile -t DISCOVERED_TEST_FILES < <(find "${ROOT}/tests" -name '*Test.php' | sed "s#^${ROOT}/##" | sort)
if [ "${#DISCOVERED_TEST_FILES[@]}" -ne "${EXPECTED_FILE_COUNT}" ]; then
  echo "Discovered test file list length mismatch" >&2
  FAIL=1
fi
for i in $(seq 0 $((EXPECTED_FILE_COUNT - 1))); do
  if [ "${DISCOVERED_TEST_FILES[$i]}" != "${EXPECTED_TEST_FILES[$i]}" ]; then
    echo "Test file manifest mismatch: expected ${EXPECTED_TEST_FILES[$i]}, got ${DISCOVERED_TEST_FILES[$i]}" >&2
    FAIL=1
    break
  fi
done

SHA="$(git -C "${ROOT}" rev-parse HEAD)"
BRANCH="$(git -C "${ROOT}" rev-parse --abbrev-ref HEAD)"
DATE="$(date -u +%Y-%m-%dT%H:%M:%SZ)"

LAST_OK="$(grep -E '^OK \(' "${LOG}" | tail -1 || true)"
TESTS="$(echo "${LAST_OK}" | sed -n 's/^OK (\([0-9]*\) tests.*/\1/p')"
ASSERTIONS="$(echo "${LAST_OK}" | sed -n 's/^OK ([0-9]* tests, \([0-9]*\) assertions).*/\1/p')"
FAILURE_LINES=0
if grep -qE '^(FAILURES!|There were [0-9]+ failures)' "${LOG}"; then
  FAILURE_LINES=1
fi
ERROR_LINES=0
if grep -qE '^(ERRORS!|There were [0-9]+ errors)' "${LOG}"; then
  ERROR_LINES=1
fi

cat > "${RESULTS}" <<EOF
# ps_emailalerts suite results

- repo: Training-Datasmith/ps_emailalerts
- date: ${DATE}
- branch: ${BRANCH}
- commit: ${SHA}
- php: 7.1.33 (${PHP_DIGEST})
- mysql: 5.7.44 (${MYSQL_DIGEST})
- phpunit: 7.5.20 (${EXPECTED_SHA})
- command: tests/bin/run-suite.sh (4 runs: default x2, random seed 20261008 x2)
- tests: ${TESTS:-unknown}
- assertions: ${ASSERTIONS:-unknown}
- failures: ${FAILURE_LINES}
- errors: ${ERROR_LINES}
- skipped/incomplete: 0
- gate exit codes: ${RC1},${RC2},${RC3},${RC4}
- expected phpunit tests: ${EXPECTED_PHPUNIT_TESTS}
- expected test files: ${EXPECTED_FILE_COUNT}
- notes: PHAR verified SHA-256 + VALIDSIG ${EXPECTED_FP}; no host PHP; MySQL digest-pinned without host port
EOF

if [ "$FAIL" -ne 0 ]; then
  echo "Suite gate failed" >&2
  tail -80 "${LOG}" >&2
  exit 1
fi

echo "Suite passed. Results: ${RESULTS}"
