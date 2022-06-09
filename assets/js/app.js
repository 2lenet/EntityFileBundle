import Dropzone from "dropzone";
import previewTemplate from "./preview-template.html";
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
        let messages;
        switch (locale) {
            case "fr":
                messages = fr;
                break;
            default:
                messages = en;
        }

        let options = {
            ...messages,
            ignoreHiddenFiles: false,
            previewTemplate: previewTemplate,
            maxFilesize: 2048,
            thumbnail: function (file, dataUrl) {

                for (let node of file.previewElement.querySelectorAll("[data-dz-remove]")) {
                    node.title = messages.dictRemoveFile;
                }
                for (let node of file.previewElement.querySelectorAll("[data-dz-download]")) {
                    node.title = messages.dictDownloadFile;
                    node.href = file.url;
                    node.download = file.path;
                }

                // override default function to disable thumbnails for non image files
                if (!file.disablePreview && file.previewElement) {
                    file.previewElement.classList.remove("dz-file-preview");
                    for (let thumbnailElement of file.previewElement.querySelectorAll("[data-dz-thumbnail]")){
                        thumbnailElement.alt = file.name;
                        thumbnailElement.src = dataUrl;
                    }

                    return setTimeout(() => file.previewElement.classList.add("dz-image-preview"), 1);
                }
            },
        };

        let dropzone = new Dropzone(form, options);

        // handle file deletion
        dropzone.on("removedfile", file => {
            if (file.deleteUrl) {
                fetch(file.deleteUrl, {
                    method: "DELETE",
                });
            }
        });

        // update delete & download url after adding a file
        dropzone.on("success", (file, responseData) => {
            file.url = responseData.url;
            file.deleteUrl = responseData.deleteUrl;

            for (let node of file.previewElement.querySelectorAll("[data-dz-download]")) {
                node.href = responseData.deleteUrl;
                node.download = file.name;
            }
        });

        let existingFiles = JSON.parse(form.dataset.files);

        // https://github.com/dropzone/dropzone/discussions/1909
        for (let file of existingFiles) {
            dropzone.displayExistingFile(file, file.url, null, null, file.resizeThumbnail);
            dropzone.files.push(file);
        }

        // TODO: add config for max files
    });
});
