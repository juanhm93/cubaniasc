export type UploadProgress = {
    loaded: number;
    total: number;
};

export class UploadAbortedError extends Error {
    constructor() {
        super('Upload aborted');
        this.name = 'UploadAbortedError';
    }
}

/**
 * PUTs a file straight to a presigned storage URL (R2), reporting progress.
 *
 * Uses XMLHttpRequest because fetch has no upload progress, and does not go
 * through `apiClient`: the request must carry no cookies, no XSRF header and no
 * JSON content type, only the headers the server signed for (minus `Host`,
 * which browsers set themselves).
 */
export function uploadToSignedUrl(
    url: string,
    file: Blob,
    headers: Record<string, string>,
    {
        onProgress,
        signal,
    }: {
        onProgress?: (progress: UploadProgress) => void;
        signal?: AbortSignal;
    } = {},
): Promise<void> {
    return new Promise((resolve, reject) => {
        if (signal?.aborted) {
            reject(new UploadAbortedError());

            return;
        }

        const xhr = new XMLHttpRequest();
        const abort = () => xhr.abort();

        xhr.open('PUT', url);

        for (const [name, value] of Object.entries(headers)) {
            if (name.toLowerCase() !== 'host') {
                xhr.setRequestHeader(name, value);
            }
        }

        xhr.upload.onprogress = (event) => {
            if (event.lengthComputable) {
                onProgress?.({ loaded: event.loaded, total: event.total });
            }
        };

        xhr.onload = () => {
            signal?.removeEventListener('abort', abort);

            if (xhr.status >= 200 && xhr.status < 300) {
                resolve();
            } else {
                reject(new Error(`Upload failed with status ${xhr.status}`));
            }
        };

        xhr.onerror = () => {
            signal?.removeEventListener('abort', abort);
            reject(new Error('Upload failed'));
        };

        xhr.onabort = () => {
            signal?.removeEventListener('abort', abort);
            reject(new UploadAbortedError());
        };

        signal?.addEventListener('abort', abort);
        xhr.send(file);
    });
}
