// Folders of a configuration with "folders: true": breadcrumb, folders of the current folder,
// and only the files of the current folder in the dropzone. Every change answers with the new listing.

const FILE_DRAG_TYPE = "application/x-lle-entity-file";
const FOLDER_DRAG_TYPE = "application/x-lle-entity-folder";

export function initFolders(container, dropzone, listing, messages, showExistingFile) {
    let state = listing;
    let currentId = null;

    const folderById = (id) => state.folders.find((folder) => folder.id === id) || null;
    const childrenOf = (id) => state.folders.filter((folder) => folder.parentId === id);
    const filesOf = (id) => state.files.filter((file) => file.folderId === id);

    const pathOf = (id) => {
        const path = [];
        for (let folder = folderById(id); folder; folder = folderById(folder.parentId)) {
            path.unshift(folder);
        }
        return path;
    };

    const element = (tag, className, text) => {
        const node = document.createElement(tag);
        if (className) {
            node.className = className;
        }
        if (text !== undefined) {
            node.textContent = text;
        }
        return node;
    };

    const button = (icon, label, className, onClick) => {
        const node = element("button", "btn btn-sm " + className);
        node.type = "button";
        node.title = label;
        node.append(element("i", "fa fa-" + icon), " ", label);
        node.addEventListener("click", onClick);
        return node;
    };

    const error = element("div", "alert alert-danger py-1 px-2 small d-none lle-entity-folders-error");

    const showError = (message) => {
        error.textContent = message || messages.error;
        error.classList.remove("d-none");
    };

    const request = async (url, method, parameters = {}) => {
        error.classList.add("d-none");
        const query = new URLSearchParams(parameters);
        const response = await fetch(method === "DELETE" ? url + "?" + query : url, {
            method: method,
            headers: state.csrfToken ? {"X-CSRF-Token": state.csrfToken} : {},
            body: method === "DELETE" ? null : query,
        });
        const data = await response.json().catch(() => ({}));

        if (!response.ok) {
            showError(data.error);
            return;
        }

        state = data;
        if (currentId !== null && !folderById(currentId)) {
            currentId = null;
        }
        render();
    };

    // A form in place of the toolbar: name of a new folder, or new name of the current one
    const askName = (toolbar, value, onSubmit) => {
        const form = element("form", "d-flex gap-1 align-items-center");
        const input = element("input", "form-control form-control-sm w-auto");
        input.value = value;
        input.required = true;
        input.maxLength = 255;
        input.placeholder = messages.folderName;
        form.append(
            input,
            button("check", messages.save, "btn-primary", () => form.requestSubmit()),
            button("times", messages.cancel, "btn-secondary", render),
        );
        form.addEventListener("submit", (event) => {
            event.preventDefault();
            onSubmit(input.value);
        });
        toolbar.replaceChildren(form);
        input.focus();
        input.select();
    };

    const acceptDrop = (node, targetId) => {
        node.addEventListener("dragover", (event) => {
            const types = event.dataTransfer.types;
            if (types.includes(FILE_DRAG_TYPE) || types.includes(FOLDER_DRAG_TYPE)) {
                event.preventDefault();
                node.classList.add("lle-entity-drop-target");
            }
        });
        node.addEventListener("dragleave", () => node.classList.remove("lle-entity-drop-target"));
        node.addEventListener("drop", (event) => {
            node.classList.remove("lle-entity-drop-target");
            const fileUrl = event.dataTransfer.getData(FILE_DRAG_TYPE);
            const folderId = event.dataTransfer.getData(FOLDER_DRAG_TYPE);
            event.preventDefault();

            if (fileUrl) {
                request(fileUrl, "POST", {folder: targetId ?? ""});
            } else if (folderId && Number(folderId) !== targetId) {
                request(folderById(Number(folderId)).moveUrl, "POST", {parent: targetId ?? ""});
            }
        });
    };

    const open = (id) => {
        // Queued files would be sent to the new folder, and their previews would be lost
        if (dropzone.getUploadingFiles().length > 0 || dropzone.getQueuedFiles().length > 0) {
            showError(messages.uploadInProgress);
            return;
        }
        currentId = id;
        render();
    };

    // Discreet icon, the label is only a tooltip
    const iconButton = (icon, label, className, onClick) => {
        const node = element("button", "btn btn-link btn-sm p-0 ms-2 text-decoration-none lle-entity-folder-action " + className);
        node.type = "button";
        node.title = label;
        node.setAttribute("aria-label", label);
        node.append(element("i", "fa fa-" + icon));
        node.addEventListener("click", onClick);
        return node;
    };

    // Breadcrumb, followed by the actions on the current folder
    const renderBreadcrumb = () => {
        const nav = element("nav", "d-flex align-items-center");
        const list = element("ol", "breadcrumb mb-0");
        const items = [{id: null, name: messages.root}, ...pathOf(currentId)];
        const current = folderById(currentId);

        items.forEach((folder, index) => {
            const item = element("li", "breadcrumb-item");
            const last = index === items.length - 1;
            const link = element(last ? "span" : "a", "lle-entity-folder-crumb", folder.name);

            if (!last) {
                link.href = "#";
                link.addEventListener("click", (event) => {
                    event.preventDefault();
                    open(folder.id);
                });
            }
            if (state.canWrite) {
                acceptDrop(link, folder.id);
            }
            item.append(link);
            list.append(item);
        });
        nav.append(list);

        if (current && state.canWrite) {
            nav.append(iconButton("pen", messages.rename, "text-secondary", () => {
                askName(nav, current.name, (name) => request(current.renameUrl, "POST", {name: name}));
            }));
        }
        if (current && state.canDelete) {
            const empty = childrenOf(current.id).length === 0 && filesOf(current.id).length === 0;
            const remove = iconButton("trash", messages.deleteFolder, "text-secondary", () => {
                // Second click to confirm, a non empty folder is deleted with its contents
                if (!remove.dataset.confirm) {
                    remove.dataset.confirm = "1";
                    remove.classList.replace("text-secondary", "text-danger");
                    remove.append(" " + (empty ? messages.confirmDelete : messages.confirmDeleteContents));
                    return;
                }
                request(current.deleteUrl, "DELETE", empty ? {} : {recursive: 1});
            });
            nav.append(remove);
        }

        return nav;
    };

    const renderToolbar = () => {
        const toolbar = element("div", "d-flex gap-1 lle-entity-folders-toolbar");

        if (state.canWrite) {
            toolbar.append(button("folder-plus", messages.newFolder, "btn-outline-primary", () => {
                askName(toolbar, "", (name) => request(state.createFolderUrl, "POST", {name: name, parent: currentId ?? ""}));
            }));
        }

        return toolbar;
    };

    const renderFolders = () => {
        const list = element("div", "d-flex flex-wrap gap-2 mb-2 lle-entity-folder-list");

        childrenOf(currentId).forEach((folder) => {
            const count = childrenOf(folder.id).length + filesOf(folder.id).length;
            const tile = element("a", "btn btn-light border lle-entity-folder");
            tile.href = "#";
            tile.dataset.folderId = folder.id;
            tile.append(element("i", "fa fa-folder me-1"), folder.name, " ", element("span", "badge bg-secondary", count));
            tile.addEventListener("click", (event) => {
                event.preventDefault();
                open(folder.id);
            });

            if (state.canWrite) {
                tile.draggable = true;
                tile.addEventListener("dragstart", (event) => event.dataTransfer.setData(FOLDER_DRAG_TYPE, folder.id));
                acceptDrop(tile, folder.id);
            }
            list.append(tile);
        });

        return list;
    };

    const makeDraggable = (file) => {
        if (state.canWrite && file.moveUrl && file.previewElement) {
            file.previewElement.draggable = true;
            file.previewElement.addEventListener("dragstart", (event) => event.dataTransfer.setData(FILE_DRAG_TYPE, file.moveUrl));
        }
    };

    // Removes the previews without removeFile(), which would delete the files on the server
    const renderFiles = () => {
        for (const file of dropzone.files) {
            file.previewElement?.remove();
        }
        dropzone.files = [];

        for (const listed of filesOf(currentId)) {
            const file = {...listed};
            showExistingFile(file);
            makeDraggable(file);
        }
        dropzone.element.classList.toggle("dz-started", dropzone.files.length > 0);
    };

    const renderFolderTree = () => {
        const header = element("div", "d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2");
        header.append(renderBreadcrumb(), renderToolbar());
        container.replaceChildren(header, error, renderFolders());
    };

    const render = () => {
        renderFolderTree();
        renderFiles();
    };

    // Uploads go to the current folder
    dropzone.on("sending", (file, xhr, formData) => formData.append("folder", currentId ?? ""));
    // Other files may still be uploading: only the folders are rendered again (counts)
    dropzone.on("success", (file, responseData) => {
        state.files.push(responseData);
        makeDraggable(file);
        renderFolderTree();
    });
    dropzone.on("removedfile", (file) => {
        state.files = state.files.filter((listed) => listed.path !== file.path);
        renderFolderTree();
    });

    render();
}
