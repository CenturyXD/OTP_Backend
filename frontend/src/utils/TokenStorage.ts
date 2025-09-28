export class TokenStorage {
    private static readonly TOKEN_KEY = 'auth_token';

    static setToken(token: string): void {
        localStorage.setItem(this.TOKEN_KEY, token);
    }

    static getToken(): string | null {
        return localStorage.getItem(this.TOKEN_KEY);
    }

    static removeToken(): void {
        localStorage.removeItem(this.TOKEN_KEY);
    }
}