let students = [];
let filteredStudents = [];

let currentPage = 1;
const itemsPerPage = 3;

fetch("students.json")
    .then(response => response.json())
    .then(data => {
        students = data;
        filteredStudents = data;

        displayStudents();
    })
    .catch(error => {
        console.error("Error loading JSON:", error);
    });

function displayStudents() {

    const list = document.getElementById("studentList");

    list.innerHTML = "";

   
    const start = (currentPage - 1) * itemsPerPage;
    const end = start + itemsPerPage;

    const pageStudents =
        filteredStudents.slice(start, end);

        pageStudents.forEach(student => {
        const div = document.createElement("div");

        div.className = "student";

        div.innerHTML = `
            <h3>${student.name}</h3>
            <p>ID: ${student.id}</p>
            <p>Branch: ${student.branch}</p>
            <p>Semester: ${student.semester}</p>
            <p>percentage: ${student.percentage}</p>
        `;

        list.appendChild(div);
    });

    displayPagination();
    document.getElementById("search") .addEventListener("input", function () {

        const searchText =this.value.toLowerCase();

        filteredStudents = students.filter(student => student.name.toLowerCase().includes(searchText));

        currentPage = 1;
        displayStudents();
    });
    document.getElementById("branchFilter").addEventListener("change", function () {

        const branch = this.value;

        if (branch === "all") {
            filteredStudents = students;
        }
        else {
            filteredStudents = students.filter(student => student.branch === branch);
        }

        currentPage = 1;

        displayStudents();
    });

    document.getElementById("sort").addEventListener("change", function () {

        const value = this.value;

        if (value === "nameAsc") {

            filteredStudents.sort((a, b) =>
                a.name.localeCompare(b.name)
            );
        }

        else if (value === "nameDesc") {

            filteredStudents.sort((a, b) =>
                b.name.localeCompare(a.name)
            );
        }

        else if (value === "marksAsc") {

            filteredStudents.sort((a, b) =>
                a.marks - b.marks
            );
        }

        else if (value === "marksDesc") {

            filteredStudents.sort((a, b) =>
                b.marks - a.marks
            );
        }

        displayStudents();
    });

    function displayPagination() {

    const pagination =document.getElementById("pagination");

    pagination.innerHTML = "";

    const totalPages = Math.ceil( filteredStudents.length / itemsPerPage);
        for (let i = 1; i <= totalPages; i++) {

        const button =  document.createElement("button");
         button.innerText = i;
         button.onclick = function () {

            currentPage = i;

            displayStudents();
        };
        pagination.appendChild(button);
     }
}
}