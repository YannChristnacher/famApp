// Fails the build when a built file refers to a code CDN, or when the
// WebAssembly file of the barcode scanner is not served by the build.
// Every file of the application must come from our own server (F01, F08).
import { readdirSync, readFileSync } from 'node:fs'
import { join, relative } from 'node:path'

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

if (!files.some((path) => SCANNER_WASM.test(path.split(/[\\/]/).pop() ?? ''))) {
  problems.push(`zxing_reader-*.wasm is missing from ${BUILD_DIRECTORY}: the barcode scanner would have nothing to load`)
}

if (problems.length > 0) {
  console.error('Build check failed: every file must be served by the application, never by a CDN.')
  problems.forEach((problem) => console.error(`- ${problem}`))
  process.exit(1)
}

console.log(`Build check passed: no CDN URL in ${files.length} built files, scanner WebAssembly served locally.`)
