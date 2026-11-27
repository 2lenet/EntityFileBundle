import Dropzone from "dropzone";
import previewTemplate from "./preview-template.html";
import {initFolders} from "./folders";
import {decorate, FILE_FIELDS} from "./files";
import fr from "./i18n/fr.json";
import en from "./i18n/en.json";

let onLoad = (callback) => {
    if (document.readyState !== "loading") {
        callback();
    } else {
        document.addEventListener("DOMContentLoaded", callback);
    }
}

onLoad(() => {
    document.querySelectorAll(".lle-entity-dropzone").forEach((form) => {

        let locale = form.dataset.locale.substring(0, 2);
        let allMessages;
        switch (locale) {
            case "fr":
                allMessages = fr;
                break;
            default:
                allMessages = en;
        }
        // "folders" and "files" hold the messages of the bundle, the other keys are Dropzone options
        let {folders: folderMessages, files: fileMessages, ...messages} = allMessages;
        let buttonMessages = {...fileMessages, dictRemoveFile: messages.dictRemoveFile, dictDownloadFile: messages.dictDownloadFile};

        let listing = form.dataset.listing ? JSON.parse(form.dataset.listing) : null;
        let decorateFile = (file) => decorate(file, listing, buttonMessages);

        let options = {
            ...messages,
            ignoreHiddenFiles: false,
            previewTemplate: previewTemplate,
            maxFilesize: 2048,
            // only a convenience, the server checks the type
            acceptedFiles: form.dataset.acceptedFiles || null,
            // override default function to disable thumbnails for non image files
            thumbnail: function (file, dataUrl) {
                if (!file.disablePreview && file.previewElement && dataUrl) {
                    file.previewElement.classList.remove("dz-file-preview");
                    for (let thumbnailElement of file.previewElement.querySelectorAll("[data-dz-thumbnail]")) {
                        thumbnailElement.alt = file.name;
                        thumbnailElement.src = dataUrl;
                    }

                    return setTimeout(() => file.previewElement.classList.add("dz-image-preview"), 1);
                }
            },
        };

        let dropzone = new Dropzone(form, options);

        // icon of the type while uploading
        dropzone.on("addedfile", decorateFile);

        // handle file deletion
        dropzone.on("removedfile", file => {
            if (file.deleteUrl) {
                fetch(file.deleteUrl, {
                    method: "DELETE",
                });
            }
        });

        // urls of the buttons after adding a file
        dropzone.on("success", (file, responseData) => {
            for (let field of FILE_FIELDS) {
                file[field] = responseData[field];
            }
            decorateFile(file);
        });

        // https://github.com/dropzone/dropzone/discussions/1909
        let showExistingFile = (file) => {
            dropzone.displayExistingFile(file, file.url, null, null, file.resizeThumbnail);
            dropzone.files.push(file);
            decorateFile(file);
        };

        let folderContainer = document.querySelector('.lle-entity-folders[data-dropzone="' + form.id + '"]');

        if (listing && listing.foldersEnabled && folderContainer) {
            // displays the files of the current folder only
            initFolders(folderContainer, dropzone, listing, folderMessages, showExistingFile);
            return;
        }

        for (let file of JSON.parse(form.dataset.files)) {
            showExistingFile(file);
        }
    });
});
