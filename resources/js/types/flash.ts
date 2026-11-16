/**
 * One-time message flashed by the server (Inertia::flash('toast', …)).
 */
export interface FlashToast {
  type: 'success' | 'error'
  message: string
}
