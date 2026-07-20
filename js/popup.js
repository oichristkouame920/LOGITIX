const popup = document.getElementById("popup");

const closePopup = document.getElementById("closePopup");

const closeBtn = document.getElementById("closeBtn");

window.addEventListener("load", ()=>{

    const alreadySeen = localStorage.getItem("popupSeen");

    if(!alreadySeen){

        setTimeout(()=>{

            popup.classList.add("show");

        },3000);

    }

});

function close(){

    popup.classList.remove("show");

    localStorage.setItem("popupSeen",true);

}

closePopup.addEventListener("click",close);

closeBtn.addEventListener("click",close);

popup.addEventListener("click",(e)=>{

    if(e.target===popup){

        close();

    }

});

document.addEventListener("keydown",(e)=>{

    if(e.key==="Escape"){

        close();

    }

});