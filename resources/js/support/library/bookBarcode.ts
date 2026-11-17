const BOOK_EAN13 = /^97[89]\d{10}$/

function ean13CheckDigit(firstTwelveDigits: string): number {
  const sum = [...firstTwelveDigits].reduce(
    (total, digit, index) => total + Number(digit) * (index % 2 === 0 ? 1 : 3),
    0,
  )

  return (10 - (sum % 10)) % 10
}

/**
 * The ISBN printed as the barcode of a book (EAN-13 starting with 978 or 979,
 * correct check digit), or null for any other barcode.
 */
export function bookIsbnFromBarcode(rawValue: string): string | null {
  const code = rawValue.trim()
  if (!BOOK_EAN13.test(code)) {
    return null
  }

  return ean13CheckDigit(code.slice(0, 12)) === Number(code[12]) ? code : null
}
