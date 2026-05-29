export class TokenManager {
  private token: string | null = null;
  private idToken: string | null = null;
  private refreshToken: string | null = null;
  private expiresAt: number | null = null;

  /**
   * Set access token and calculate expiry time
   */
  setToken(token: string, expiresIn: number): void {
    this.token = token;
    this.expiresAt = Date.now() + expiresIn * 1000;
    this.refreshToken = null;
  }

  /**
   * Set id token
   */
  setIdToken(idToken: string): void {
    this.idToken = idToken;
  }

  /**
   * Get id token if valid, otherwise clear memory
   */
  getIdToken(): string | null {
    if (!this.idToken || !this.expiresAt) return null;
    if (Date.now() >= this.expiresAt) {
      this.clearToken();
      return null;
    }
    return this.idToken;
  }

  /**
   * Get access token if valid, otherwise clear memory
   */
  getToken(): string | null {
    if (!this.token || !this.expiresAt) return null;
    if (Date.now() >= this.expiresAt) {
      this.clearToken();
      return null;
    }
    return this.token;
  }

  /**
   * Clear all tokens from memory
   */
  clearToken(): void {
    this.token = null;
    this.idToken = null;
    this.refreshToken = null;
    this.expiresAt = null;
  }

  /**
   * Check if token is present and valid
   */
  isAuthenticated(): boolean {
    return this.getToken() !== null;
  }
}

export const tokenManager = new TokenManager();
