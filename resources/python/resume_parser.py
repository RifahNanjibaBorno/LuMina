"""Local resume extraction: pypdf for PDF, standard library for DOCX."""
import json
import re
import sys
import zipfile
from xml.etree import ElementTree


def extract_text(path, extension):
    if extension == "pdf":
        from pypdf import PdfReader
        reader = PdfReader(path)
        if len(reader.pages) > 30:
            raise ValueError("Resume exceeds 30 pages")
        return "\n".join(page.extract_text() or "" for page in reader.pages)
    if extension != "docx":
        raise ValueError("Unsupported format")
    with zipfile.ZipFile(path) as document:
        entry = document.getinfo("word/document.xml")
        if entry.file_size > 5_000_000:
            raise ValueError("Document is too large")
        root = ElementTree.fromstring(document.read(entry))
        ns = {"w": "http://schemas.openxmlformats.org/wordprocessingml/2006/main"}
        return "\n".join("".join(p.itertext()) for p in root.findall(".//w:p", ns))


def parse(text):
    lines = [line.strip() for line in text.splitlines() if line.strip()]
    output = {"text_found": bool(lines)}
    if not lines:
        return output
    heading_map = {
        "education": "education", "academic background": "education",
        "educational qualification": "education", "educational qualifications": "education",
        "skills": "skills", "technical skills": "skills", "core skills": "skills",
        "projects": "projects", "academic projects": "projects", "personal projects": "projects",
        "certifications": "certifications", "certificates": "certifications",
        "achievements": "achievements", "awards": "achievements", "honors": "achievements",
        "experience": "experience", "work experience": "experience", "employment history": "experience",
        "summary": "bio", "profile": "bio", "objective": "bio", "about me": "bio",
    }
    current = None
    sections = {}
    for line in lines:
        normalized = re.sub(r"[^a-z ]", "", line.lower()).strip()
        if normalized in heading_map:
            current = heading_map[normalized]
            sections.setdefault(current, [])
        elif ":" in line and line.split(":", 1)[0].strip().lower() in heading_map:
            heading, value = line.split(":", 1)
            current = heading_map[heading.strip().lower()]
            sections.setdefault(current, []).append(value.strip())
        elif current:
            sections[current].append(line)
    for key, content in sections.items():
        output[key] = "\n".join(content)[:2000 if key in {"bio", "skills"} else 6000]
    first = re.sub(r"^(name\s*[:\-]\s*)", "", lines[0], flags=re.I)
    if 2 <= len(first.split()) <= 6 and not re.search(r"[@\d]|resume|curriculum|vitae", first, re.I):
        output["name"] = first[:100]
    email = re.search(r"[\w.+-]+@[\w.-]+\.[a-zA-Z]{2,}", text)
    if email:
        output["email"] = email.group(0)
    phone = re.search(r"(?:\+?880[\s-]?)?0?1[3-9](?:[\s-]?\d){8}\b", text)
    if phone:
        output["phone"] = phone.group(0)
    for key, domain in [("github", "github.com"), ("linkedin", "linkedin.com/in")]:
        link = re.search(r"(?:https?://)?(?:www\.)?" + re.escape(domain) + r"/[\w./-]+", text)
        if link:
            url = link.group(0).rstrip("./")
            output[key] = url if url.startswith("http") else "https://" + url
    return output


if __name__ == "__main__":
    try:
        print(json.dumps(parse(extract_text(sys.argv[1], sys.argv[2])), ensure_ascii=False))
    except Exception as error:
        print(str(error), file=sys.stderr)
        sys.exit(1)
