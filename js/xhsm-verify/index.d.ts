export type SignatureFormat = 'der-hex' | 'raw-hex' | 'der-base64' | 'raw-base64'
export interface VerifyOptions {
  format: SignatureFormat
  userId?: string
}
export declare function verifySignature(msg: string, sig: string, publicKey: string, options: VerifyOptions): boolean
export declare function base64ToHex(base64: string): string
export declare function hexToBase64(hex: string): string
