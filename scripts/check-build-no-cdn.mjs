// Fails the build when a built file refers to a code CDN, or when the
// WebAssembly file of the barcode scanner is not served by the build, or is
// not the one expected by barcode-detector (the app imports it from zxing-wasm,
// a dependency of barcode-detector that package.json does not declare).
// Every file of the application must come from our own server (F01, F08).
import { createHash } from 'node:crypto'
import { readdirSync, readFileSync } from 'node:fs'
import { join, relative } from 'node:path'
import { ZXING_WASM_SHA256, ZXING_WASM_VERSION } from 'barcode-detector/ponyfill'

const BUILD_DIRECTORY = 'public/build'
const TEXT_FILE = /\.(js|mjs|css|html|json|map)$/
const CDN_HOSTS = /\b(?:[a-z0-9-]+\.)*(?:jsdelivr\.net|unpkg\.com|cdnjs\.cloudflare\.com|esm\.sh|skypack\.dev|jspm\.io|jspm\.dev)\b/gi
const SCANNER_WASM = /^zxing_reader-[\w-]+\.wasm$/

function filesIn(directory) {
  return readdirSync(directory, { withFileTypes: true }).flatMap((entry) => {
    const path = join(directory, entry.name)

    return entry.isDirectory() ? filesIn(path) : [path]
  })
}

const files = filesIn(BUILD_DIRECTORY)
const problems = []

for (const file of files.filter((path) => TEXT_FILE.test(path))) {
  const hosts = new Set(readFileSync(file, 'utf8').match(CDN_HOSTS) ?? [])
  if (hosts.size > 0) {
    problems.push(`${relative('.', file)} refers to ${[...hosts].join(', ')}`)
  }
}

const scannerWasm = files.filter((path) => SCANNER_WASM.test(path.split(/[\\/]/).pop() ?? ''))
if (scannerWasm.length === 0) {
  problems.push(`zxing_reader-*.wasm is missing from ${BUILD_DIRECTORY}: the barcode scanner would have nothing to load`)
}
for (const file of scannerWasm) {
  const sha256 = createHash('sha256').update(readFileSync(file)).digest('hex')
  if (sha256 !== ZXING_WASM_SHA256) {
    problems.push(`${relative('.', file)} is not the zxing_reader.wasm ${ZXING_WASM_VERSION} expected by barcode-detector (SHA-256 ${sha256} instead of ${ZXING_WASM_SHA256})`)
  }
}

if (problems.length > 0) {
  console.error('Build check failed (no CDN, scanner WebAssembly served by the build and matching barcode-detector):')
  problems.forEach((problem) => console.error(`- ${problem}`))
  process.exit(1)
}

console.log(`Build check passed: no CDN URL in ${files.length} built files, scanner WebAssembly ${ZXING_WASM_VERSION} served locally.`)
