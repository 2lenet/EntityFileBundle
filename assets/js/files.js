// Buttons and picture of each file: download, preview, public link, thumbnail or icon of its type.

const ICONS = [
    [/pdf$/, "file-pdf"],
    [/msword|wordprocessingml|opendocument\.text/, "file-word"],
    [/ms-excel|spreadsheetml|opendocument\.spreadsheet|csv/, "file-excel"],
    [/ms-powerpoint|presentationml|opendocument\.presentation/, "file-powerpoint"],
    [/^image\//, "file-image"],
    [/zip|rar|7z|tar|gzip/, "file-archive"],
    [/^text\//, "file-alt"],
];
const LINK_COPIED_DELAY = 2000;

// Fields of the upload response copied on the uploaded File (its name and size are read-only)
export const FILE_FIELDS = ["url", "deleteUrl", "path", "moveUrl", "folderId", "mimeType", "previewUrl", "thumbnailUrl", "publicLinkUrl"];

const iconOf = (mimeType) => (ICONS.find(([pattern]) => pattern.test(mimeType || "")) || [null, "file"])[1];

const copy = async (text) => {
    try {
        await navigator.clipboard.writeText(text);
        return true;
    } catch (error) {
        // Without the Clipboard API (page served over HTTP)
        const field = document.createElement("textarea");
        field.value = text;
        field.setAttribute("readonly", "");
        field.style.position = "fixed";
        field.style.opacity = "0";
        document.body.append(field);
        field.select();
        const copied = document.execCommand("copy");
        field.remove();
        return copied;
    }
};

export function openPreview(file, messages) {
    const backdrop = document.createElement("div");
    backdrop.className = "lle-entity-preview";
    backdrop.setAttribute("role", "dialog");
    backdrop.setAttribute("aria-modal", "true");

    const dialog = document.createElement("div");
    dialog.className = "lle-entity-preview-dialog";

    const header = document.createElement("div");
    header.className = "lle-entity-preview-header";
    const title = document.createElement("span");
    title.className = "text-truncate";
    title.textContent = file.name;
    const close = document.createElement("button");
    close.type = "button";
    close.className = "btn-close";
    close.title = messages.close;
    close.setAttribute("aria-label", messages.close);
    header.append(title, close);

    const content = (file.mimeType || "").startsWith("image/")
        ? Object.assign(document.createElement("img"), {src: file.previewUrl, alt: file.name})
        : Object.assign(document.createElement("iframe"), {src: file.previewUrl, title: file.name});
    content.className = "lle-entity-preview-content";

    dialog.append(header, content);
    backdrop.append(dialog);

    const onKey = (event) => event.key === "Escape" && hide();
    const hide = () => {
        backdrop.remove();
        document.removeEventListener("keydown", onKey);
    };
    close.addEventListener("click", hide);
    backdrop.addEventListener("click", (event) => event.target === backdrop && hide());
    document.addEventListener("keydown", onKey);

    document.body.append(backdrop);
    close.focus();
}

/**
 * @param file a file displayed by Dropzone, with the fields of the listing
 * @param listing the listing of the brick (csrfToken, canSharePublicLink)
 * @param messages the "files" messages, plus dictRemoveFile and dictDownloadFile
 */
export function decorate(file, listing, messages) {
    const preview = file.previewElement;
    if (!preview) {
        return;
    }

    for (const node of preview.querySelectorAll("[data-dz-remove]")) {
        node.title = messages.dictRemoveFile;
    }
    for (const node of preview.querySelectorAll("[data-dz-download]")) {
        node.title = messages.dictDownloadFile;
        node.href = file.url;
        node.download = file.name;
    }

    // Click on the file: preview of a PDF or an image, download of the other types
    for (const node of preview.querySelectorAll(".dz-image, .dz-details")) {
        node.classList.add("lle-entity-file-open");
        node.title = file.previewUrl ? messages.preview : messages.dictDownloadFile;
        node.onclick = (event) => {
            if (!file.url) {
                return; // still uploading
            }
            event.stopPropagation();
            if (file.previewUrl) {
                openPreview(file, messages);
            } else {
                preview.querySelector("[data-dz-download]")?.click();
            }
        };
    }

    for (const node of preview.querySelectorAll("[data-lle-copy-link]")) {
        node.title = messages.copyLink;
        node.classList.toggle("d-none", !(listing && listing.canSharePublicLink && file.publicLinkUrl));
        node.onclick = async () => {
            const icon = node.querySelector("i");
            const response = await fetch(file.publicLinkUrl, {
                method: "POST",
                headers: listing.csrfToken ? {"X-CSRF-Token": listing.csrfToken} : {},
            });
            const data = await response.json().catch(() => ({}));
            const copied = response.ok && data.url && await copy(data.url);

            node.title = copied ? messages.linkCopied : messages.copyError;
            icon.className = "fa fa-" + (copied ? "check" : "times");
            setTimeout(() => {
                node.title = messages.copyLink;
                icon.className = "fa fa-link";
            }, LINK_COPIED_DELAY);
        };
    }

    for (const node of preview.querySelectorAll("[data-lle-icon]")) {
        node.className = "fa fa-" + iconOf(file.mimeType || file.type) + " lle-entity-file-icon";
    }

    // Thumbnail of a PDF, generated by the server; the icon stays if it cannot be generated (unreadable PDF)
    if (file.thumbnailUrl) {
        for (const image of preview.querySelectorAll("[data-dz-thumbnail]")) {
            image.onload = () => {
                image.alt = file.name;
                preview.classList.remove("dz-file-preview");
                preview.classList.add("dz-image-preview", "lle-entity-has-thumbnail");
            };
            image.onerror = () => {
                image.removeAttribute("src");
                image.removeAttribute("alt");
            };
            image.src = file.thumbnailUrl;
        }
    }
}
