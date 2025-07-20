
document.addEventListener("DOMContentLoaded", () => {
    const link_token = document.getElementById("link-button").dataset.token;

    const handler = Plaid.create({
        token: link_token,
        onSuccess: function (public_token, metadata) {
            fetch("/plaid/exchange", {
                method: "POST",
                headers: {
                    "X-CSRF-TOKEN": document.querySelector(
                        'meta[name="csrf-token"]'
                    ).content,
                    "Content-Type": "application/json",
                },
                body: JSON.stringify({ public_token }),
            })
                .then((res) => res.json())
                .then((data) => {
                    alert("Bank connected!");
                    console.log(data);
                });
        },
        onExit: function (err, metadata) {
            console.error("Plaid exited", err);
        },
    });

    document.getElementById("link-button").addEventListener("click", () => {
        handler.open();
    });
});
