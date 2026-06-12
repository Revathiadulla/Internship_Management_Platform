<?php

$all_questions = [];

// ============================================================================
// FRONTEND DEVELOPMENT - 30 questions
// ============================================================================
$all_questions["Frontend Development"] = [
  ["q" => "Which HTML5 tag is used for self-contained content like illustrations?", "options" => ["<section>", "<figure>", "<aside>", "<img>"], "correct" => 1],
  ["q" => "What does 'box-sizing: border-box' do in CSS?", "options" => ["Includes padding and border in total width/height", "Excludes padding from width", "Forces border grid", "Adds borders to margins"], "correct" => 0],
  ["q" => "What is the primary state hook in React?", "options" => ["useEffect", "useContext", "useState", "useReducer"], "correct" => 2],
  ["q" => "Which JS array method returns a new array of elements passing a test?", "options" => ["map()", "filter()", "forEach()", "reduce()"], "correct" => 1],
  ["q" => "What does the CSS 'flexbox' property 'justify-content: space-between' do?", "options" => ["Centers items", "Distributes items with equal space between", "Aligns items to start", "Stretches items"], "correct" => 1],
  ["q" => "Which HTML attribute makes an input field required before form submission?", "options" => ["mandatory", "required", "validate", "must"], "correct" => 1],
  ["q" => "What is the purpose of the 'key' prop in React lists?", "options" => ["Styling list items", "Helping React identify changed items", "Setting item order", "Encrypting data"], "correct" => 1],
  ["q" => "Which CSS unit is relative to the root element's font size?", "options" => ["em", "rem", "px", "vh"], "correct" => 1],
  ["q" => "What does 'async/await' do in JavaScript?", "options" => ["Runs code synchronously", "Handles asynchronous operations more readably", "Blocks the main thread", "Creates new threads"], "correct" => 1],
  ["q" => "Which method is used to add an event listener in JavaScript?", "options" => ["attachEvent()", "addEventListener()", "onEvent()", "bindEvent()"], "correct" => 1],
  ["q" => "What is the Virtual DOM in React?", "options" => ["A server-side DOM", "A lightweight copy of the real DOM", "A CSS framework", "A database"], "correct" => 1],
  ["q" => "Which CSS property controls the stacking order of elements?", "options" => ["position", "z-index", "display", "overflow"], "correct" => 1],
  ["q" => "What does 'localStorage' do in the browser?", "options" => ["Stores data on the server", "Stores data persistently in the browser", "Stores session-only data", "Encrypts cookies"], "correct" => 1],
  ["q" => "Which HTML tag is used to link an external CSS file?", "options" => ["<style>", "<link>", "<script>", "<css>"], "correct" => 1],
  ["q" => "What is the purpose of 'useEffect' in React?", "options" => ["Manage state", "Handle side effects like API calls", "Style components", "Route navigation"], "correct" => 1],
  ["q" => "Which CSS selector targets an element with a specific class?", "options" => ["#class", "*class", ".class", "@class"], "correct" => 2],
  ["q" => "What does 'Promise.all()' do in JavaScript?", "options" => ["Runs promises sequentially", "Runs all promises in parallel and waits for all", "Cancels all promises", "Returns the first resolved promise"], "correct" => 1],
  ["q" => "Which HTTP method is used to send data to a server to create a resource?", "options" => ["GET", "PUT", "POST", "DELETE"], "correct" => 2],
  ["q" => "What is 'prop drilling' in React?", "options" => ["A CSS technique", "Passing props through multiple component layers", "A testing method", "A build optimization"], "correct" => 1],
  ["q" => "Which CSS property makes an element invisible but still occupies space?", "options" => ["display: none", "visibility: hidden", "opacity: 0", "Both B and C"], "correct" => 3],
  ["q" => "What is the purpose of 'webpack' in frontend development?", "options" => ["Testing framework", "Module bundler", "CSS preprocessor", "State manager"], "correct" => 1],
  ["q" => "Which JavaScript method converts a JSON string to an object?", "options" => ["JSON.stringify()", "JSON.parse()", "JSON.convert()", "JSON.decode()"], "correct" => 1],
  ["q" => "What does 'CSS Grid' provide over 'Flexbox'?", "options" => ["Better browser support", "Two-dimensional layout control", "Faster rendering", "Simpler syntax"], "correct" => 1],
  ["q" => "Which React hook is used to access context values?", "options" => ["useState", "useRef", "useContext", "useMemo"], "correct" => 2],
  ["q" => "What is 'debouncing' in JavaScript?", "options" => ["Removing event listeners", "Delaying function execution until after a pause", "Caching API responses", "Compressing images"], "correct" => 1],
  ["q" => "Which attribute is used to define inline styles in HTML?", "options" => ["class", "id", "style", "css"], "correct" => 2],
  ["q" => "What does 'npm install' do?", "options" => ["Runs the app", "Installs project dependencies", "Builds the project", "Tests the code"], "correct" => 1],
  ["q" => "Which CSS pseudo-class targets the first child element?", "options" => [":first", "first-child", ":first-child", "::first-child"], "correct" => 2],
  ["q" => "What is 'lazy loading' in web development?", "options" => ["Loading all resources upfront", "Loading resources only when needed", "Caching resources", "Compressing resources"], "correct" => 1],
  ["q" => "Which tool is commonly used for frontend unit testing in React?", "options" => ["Selenium", "Jest", "Mocha", "Cypress"], "correct" => 1]
];

// ============================================================================
// BACKEND DEVELOPMENT - 30 questions
// ============================================================================
$all_questions["Backend Development"] = [
  ["q" => "Which HTTP status code represents successful resource creation?", "options" => ["200 OK", "201 Created", "302 Found", "400 Bad Request"], "correct" => 1],
  ["q" => "What is the primary security advantage of prepared statements in SQL?", "options" => ["Speeds up queries", "Prevents SQL injection", "Reduces CPU load", "Auto-indexes tables"], "correct" => 1],
  ["q" => "In Node.js, what is 'npm' primarily used for?", "options" => ["Node Process Monitor", "Node Package Manager", "Node Protocol Method", "Node Path Module"], "correct" => 1],
  ["q" => "Which database type is non-relational and document-oriented?", "options" => ["PostgreSQL", "MySQL", "MongoDB", "SQLite"], "correct" => 2],
  ["q" => "What does REST stand for in API design?", "options" => ["Remote Execution State Transfer", "Representational State Transfer", "Resource Endpoint Standard Transfer", "Relational Entity State Transfer"], "correct" => 1],
  ["q" => "Which HTTP method is idempotent and used to update a resource completely?", "options" => ["POST", "PATCH", "PUT", "DELETE"], "correct" => 2],
  ["q" => "What is JWT used for in web applications?", "options" => ["Database queries", "Stateless authentication", "File uploads", "CSS styling"], "correct" => 1],
  ["q" => "Which PHP function is used to start a session?", "options" => ["session_open()", "start_session()", "session_start()", "init_session()"], "correct" => 2],
  ["q" => "What is the purpose of middleware in Express.js?", "options" => ["Styling routes", "Processing requests before they reach route handlers", "Managing databases", "Compiling TypeScript"], "correct" => 1],
  ["q" => "Which SQL clause is used to filter groups after aggregation?", "options" => ["WHERE", "HAVING", "GROUP BY", "ORDER BY"], "correct" => 1],
  ["q" => "What does 'ORM' stand for in backend development?", "options" => ["Object Relational Mapping", "Open Resource Management", "Optimized Request Model", "Object Route Manager"], "correct" => 0],
  ["q" => "Which HTTP status code means 'Unauthorized'?", "options" => ["403", "404", "401", "500"], "correct" => 2],
  ["q" => "What is the purpose of 'bcrypt' in backend development?", "options" => ["Compressing files", "Hashing passwords securely", "Encrypting API keys", "Managing sessions"], "correct" => 1],
  ["q" => "Which SQL command removes all rows from a table without logging individual deletions?", "options" => ["DELETE", "DROP", "TRUNCATE", "REMOVE"], "correct" => 2],
  ["q" => "What is a foreign key in a relational database?", "options" => ["A primary identifier", "A key that references a primary key in another table", "An encrypted key", "A unique index"], "correct" => 1],
  ["q" => "Which Node.js module is used to create an HTTP server natively?", "options" => ["fs", "path", "http", "url"], "correct" => 2],
  ["q" => "What does 'CORS' stand for?", "options" => ["Cross-Origin Resource Sharing", "Cross-Object Request Standard", "Client-Origin Resource Service", "Content-Origin Request System"], "correct" => 0],
  ["q" => "Which design pattern separates application logic into Model, View, Controller?", "options" => ["Singleton", "Observer", "MVC", "Factory"], "correct" => 2],
  ["q" => "What is the purpose of an index in a database?", "options" => ["Encrypting data", "Speeding up data retrieval", "Backing up data", "Normalizing tables"], "correct" => 1],
  ["q" => "Which PHP superglobal contains form data sent via POST?", "options" => ['$_GET', '$_POST', '$_REQUEST', '$_FORM'], "correct" => 1],
  ["q" => "What is 'connection pooling' in databases?", "options" => ["Reusing existing database connections", "Creating new connections for each request", "Encrypting connections", "Caching query results"], "correct" => 0],
  ["q" => "Which HTTP header is used to specify the content type of a request body?", "options" => ["Accept", "Authorization", "Content-Type", "X-Request-ID"], "correct" => 2],
  ["q" => "What does 'normalization' in database design achieve?", "options" => ["Faster queries", "Reducing data redundancy", "Encrypting data", "Increasing storage"], "correct" => 1],
  ["q" => "Which command in MySQL shows all tables in the current database?", "options" => ["LIST TABLES", "SHOW TABLES", "GET TABLES", "DISPLAY TABLES"], "correct" => 1],
  ["q" => "What is the purpose of 'rate limiting' in APIs?", "options" => ["Speeding up responses", "Preventing abuse by limiting request frequency", "Caching responses", "Compressing data"], "correct" => 1],
  ["q" => "Which PHP function prevents XSS by converting special characters to HTML entities?", "options" => ["strip_tags()", "htmlspecialchars()", "sanitize()", "escape()"], "correct" => 1],
  ["q" => "What is a 'microservice' architecture?", "options" => ["A single large application", "Small independent services communicating via APIs", "A frontend framework", "A database design pattern"], "correct" => 1],
  ["q" => "Which SQL JOIN returns all rows from both tables, with NULLs for non-matching rows?", "options" => ["INNER JOIN", "LEFT JOIN", "RIGHT JOIN", "FULL OUTER JOIN"], "correct" => 3],
  ["q" => "What does 'idempotent' mean in the context of HTTP methods?", "options" => ["The request changes state each time", "Multiple identical requests produce the same result", "The request is encrypted", "The request requires authentication"], "correct" => 1],
  ["q" => "Which tool is commonly used for API testing?", "options" => ["Figma", "Postman", "Webpack", "Jest"], "correct" => 1]
];

// ============================================================================
// DATA SCIENCE - 30 questions
// ============================================================================
$all_questions["Data Science"] = [
  ["q" => "Which Python library is primarily used for high-performance data manipulation and analysis?", "options" => ["Matplotlib", "NumPy", "Pandas", "Scikit-Learn"], "correct" => 2],
  ["q" => "What SQL clause is used to filter group results after an aggregation has been performed?", "options" => ["WHERE", "HAVING", "GROUP BY", "ORDER BY"], "correct" => 1],
  ["q" => "Which SQL command is used to remove all records from a table without logging individual row deletions?", "options" => ["DELETE", "DROP", "TRUNCATE", "REMOVE"], "correct" => 2],
  ["q" => "What is the default behavior of the pandas.dropna() function?", "options" => ["Fills missing values with 0", "Drops columns containing NaNs", "Drops rows containing NaNs", "Interpolates missing values"], "correct" => 2],
  ["q" => "Which of the following is a supervised learning algorithm?", "options" => ["K-Means Clustering", "Random Forest", "PCA", "Apriori Algorithm"], "correct" => 1],
  ["q" => "Which technique is used to cluster unlabeled data into groups?", "options" => ["K-Means Clustering", "Linear Regression", "Support Vector Machines", "Logistic Regression"], "correct" => 0],
  ["q" => "What does the acronym 'NaN' stand for in pandas and numpy?", "options" => ["New and Null", "Not a Number", "Node adjacent Number", "None and Null"], "correct" => 1],
  ["q" => "Which measure of central tendency is most sensitive to extreme outliers?", "options" => ["Mean", "Median", "Mode", "Geometric Mean"], "correct" => 0],
  ["q" => "What is a common solution to the problem of overfitting in machine learning models?", "options" => ["Increasing model complexity", "Applying regularization (e.g. L1/L2)", "Removing validation data", "Using less training data"], "correct" => 1],
  ["q" => "Which SQL JOIN returns all rows from the left table and matched rows from the right table?", "options" => ["INNER JOIN", "LEFT JOIN", "RIGHT JOIN", "FULL OUTER JOIN"], "correct" => 1],
  ["q" => "Which Matplotlib function is used to create a scatter plot?", "options" => ["plot()", "scatter()", "hist()", "bar()"], "correct" => 1],
  ["q" => "What is the main purpose of using cross-validation in machine learning?", "options" => ["To speed up training time", "To evaluate how well a model generalizes to unseen data", "To compress the dataset", "To select features automatically"], "correct" => 1],
  ["q" => "Which NumPy function is used to create an array of all zeros?", "options" => ["zeros()", "empty()", "ones()", "arange()"], "correct" => 0],
  ["q" => "What is a clear indicator of underfitting in a machine learning model?", "options" => ["Low training error, high testing error", "High training error, high testing error", "Low training error, low testing error", "High training error, low testing error"], "correct" => 1],
  ["q" => "Which of the following is a standard classification evaluation metric?", "options" => ["Mean Squared Error (MSE)", "R-squared", "F1-Score", "Mean Absolute Error (MAE)"], "correct" => 2],
  ["q" => "Which technique is used to scale features so they have a range between 0 and 1?", "options" => ["Standardization (Z-score)", "Min-Max Normalization", "Log Transformation", "One-Hot Encoding"], "correct" => 1],
  ["q" => "Which method is commonly used to handle highly imbalanced datasets in classification?", "options" => ["Principal Component Analysis (PCA)", "Synthetic Minority Over-sampling Technique (SMOTE)", "Grid Search", "Lasso Regression"], "correct" => 1],
  ["q" => "Which activation function is commonly used in the output layer of a binary classification neural network?", "options" => ["Sigmoid", "ReLU", "Tanh", "Softmax"], "correct" => 0],
  ["q" => "What is the primary objective of Principal Component Analysis (PCA)?", "options" => ["Feature engineering", "Increasing model complexity", "Dimensionality reduction", "Clustering similar data points"], "correct" => 2],
  ["q" => "In regression, what does the R-squared value represent?", "options" => ["The average error rate of the model", "The proportion of variance in the dependent variable explained by the model", "The statistical significance of the intercept", "The slope of the regression line"], "correct" => 1],
  ["q" => "Which pandas method is used to split data into groups based on some criteria?", "options" => ["split()", "groupby()", "pivot()", "aggregate()"], "correct" => 1],
  ["q" => "Which metric measures the distance or difference between two probability distributions?", "options" => ["Kullback-Leibler (KL) Divergence", "Euclidean Distance", "Manhattan Distance", "Cosine Similarity"], "correct" => 0],
  ["q" => "How is standard deviation mathematically defined in relation to variance?", "options" => ["It is the square of variance", "It is the square root of variance", "It is half of variance", "It is variance multiplied by mean"], "correct" => 1],
  ["q" => "Which of the following is a popular optimization algorithm used in training neural networks?", "options" => ["K-Nearest Neighbors", "L-BFGS", "Adam", "Decision Trees"], "correct" => 2],
  ["q" => "What type of database index is most efficient for range-based queries?", "options" => ["Hash Index", "B-Tree Index", "Bitmap Index", "Spatial Index"], "correct" => 1],
  ["q" => "Which machine learning algorithm is directly based on Bayes' Theorem?", "options" => ["Naive Bayes", "Support Vector Machines", "K-Means", "Gradient Boosting"], "correct" => 0],
  ["q" => "Which open-source Python library is widely used for deep learning?", "options" => ["Scikit-Learn", "SciPy", "PyTorch", "Statsmodels"], "correct" => 2],
  ["q" => "What is the process of replacing missing values with estimated values called?", "options" => ["Normalization", "Imputation", "Outlier detection", "Discretization"], "correct" => 1],
  ["q" => "Which type of plot is best suited for showing the distribution of a single numerical variable?", "options" => ["Line Plot", "Bar Chart", "Histogram", "Pie Chart"], "correct" => 2],
  ["q" => "Which ensemble learning method builds trees sequentially to minimize residual errors?", "options" => ["Random Forest", "Gradient Boosting", "Bagging", "Voting Classifier"], "correct" => 1]
];

// ============================================================================
// UI/UX DESIGN - 30 questions
// ============================================================================
$all_questions["UI/UX Design"] = [
  ["q" => "What does the acronym 'UX' stand for in product design?", "options" => ["User Experience", "User Extension", "Universal Experience", "User Expansion"], "correct" => 0],
  ["q" => "Which design principle refers to the arrangement of elements to imply importance or visual order?", "options" => ["Contrast", "Alignment", "Hierarchy", "Repetition"], "correct" => 2],
  ["q" => "What is a wireframe in user interface design?", "options" => ["A high-fidelity colored mockup", "A basic visual guide representing the skeletal framework", "A functional interactive prototype", "A database schema diagram"], "correct" => 1],
  ["q" => "What is Figma primarily used for by product teams?", "options" => ["Backend development", "Interface design and collaborative prototyping", "Video editing", "Database queries"], "correct" => 1],
  ["q" => "Why is color contrast ratio important in web accessibility?", "options" => ["It speeds up page loading time", "It ensures text is readable for users with visual impairments", "It prevents security vulnerabilities", "It improves search engine optimization"], "correct" => 1],
  ["q" => "What does the term 'CTA' mean in UI design?", "options" => ["Call to Action", "Central Testing Area", "Customer Target Audience", "Component Type Attribute"], "correct" => 0],
  ["q" => "What is the primary goal of conducting usability testing?", "options" => ["To find backend logic errors", "To test server request speed", "To identify user friction and areas of improvement in the design", "To finalize visual branding guidelines"], "correct" => 2],
  ["q" => "Which characteristic is typical of a high-fidelity prototype?", "options" => ["It uses simple gray boxes with no text", "It looks and behaves very close to the final product with actual assets", "It is drawn on a physical whiteboard", "It lacks user interactions"], "correct" => 1],
  ["q" => "What is the main focus of Fitts's Law in user interface design?", "options" => ["The size and distance of interactive targets relative to selection time", "The emotional connection a user makes with color", "The speed at which a page renders in different browsers", "The grid alignment of font sizes"], "correct" => 0],
  ["q" => "What is a 'design system'?", "options" => ["A collection of programming libraries", "A set of reusable visual components, standards, and patterns", "A database of client profiles", "A framework for server-side hosting"], "correct" => 1],
  ["q" => "What is a micro-interaction in a user interface?", "options" => ["A backend database trigger", "A subtle animation or response to user action (e.g., button hover change)", "A small screen layout (e.g., mobile view)", "A short user survey"], "correct" => 1],
  ["q" => "In typography, what does 'kerning' refer to?", "options" => ["The vertical spacing between lines of text", "The horizontal spacing between individual characters", "The choice of font weight", "The style of decorative serifs"], "correct" => 1],
  ["q" => "What is a User Persona in UX research?", "options" => ["The actual photo of the target user", "A legal contract with research participants", "A semi-fictional representation of the target user based on data", "The system's database model of users"], "correct" => 2],
  ["q" => "What does the 'mobile-first' design principle state?", "options" => ["Always create a mobile app before a web app", "Design the interface for the smallest screen size first, then scale up", "Block desktop users from accessing the site", "Mobile screens should look identical to desktop screens"], "correct" => 1],
  ["q" => "Which Gestalt principle describes the tendency to perceive elements close to each other as a group?", "options" => ["Proximity", "Similarity", "Continuity", "Closure"], "correct" => 0],
  ["q" => "What does the acronym 'IA' stand for in UX design?", "options" => ["Interaction Analysis", "Information Architecture", "Interface Adaptability", "Interactive Assets"], "correct" => 1],
  ["q" => "What is a 'Dark Pattern' in user interface design?", "options" => ["A dark-themed user interface layout", "A user interface designed to trick users into doing something they did not intend", "A broken CSS style sheet", "An unstyled web page structure"], "correct" => 1],
  ["q" => "What is 'responsive design'?", "options" => ["An interface that responds quickly to user click actions", "A layout that adapts fluidly to different screen dimensions and devices", "An application that runs entirely on local clients", "A voice-assisted interface design"], "correct" => 1],
  ["q" => "What is the main purpose of a card layout in user interface design?", "options" => ["To group related information and actions into a single container", "To simulate a deck of playing cards", "To display infinite lists of unformatted text", "To create decorative patterns"], "correct" => 0],
  ["q" => "What is A/B testing in user experience design?", "options" => ["Testing two components inside the same web page", "Comparing two versions of a page or interface to see which performs better", "Running tests on Apple and Android platforms", "Testing code compilation before and after a build"], "correct" => 1],
  ["q" => "What is the Golden Ratio value approximately used in visual design?", "options" => ["1.414", "1.618", "2.718", "3.142"], "correct" => 1],
  ["q" => "What is Skeuomorphism in user interface design?", "options" => ["A flat design style using solid colors only", "A futuristic layout using neon elements", "A style where digital items mimic real-world textures and objects", "A design system with no structural grid"], "correct" => 2],
  ["q" => "What is the primary focus of a heuristic evaluation?", "options" => ["Measuring server performance logs", "Usability inspection of an interface against standard design guidelines", "Checking SQL queries for security injection", "Validating form fields using regex"], "correct" => 1],
  ["q" => "Which of the following is considered a redundant or confusing UI pattern?", "options" => ["Having a search bar on a content site", "Showing a hamburger menu when all links are already fully visible on desktop", "Using tooltips to explain complex terms", "Highlighting selected states in a navigation menu"], "correct" => 1],
  ["q" => "What is the purpose of a breadcrumb trail in a website's UI?", "options" => ["To show the user's current location within the site's hierarchical structure", "To track user cookies persistently", "To provide promotional links", "To design custom button hover effects"], "correct" => 0],
  ["q" => "What is the main advantage of SVG files over PNG files for UI icons?", "options" => ["They load slower but look prettier", "They scale infinitely without losing resolution quality", "They support sound embedding", "They are highly secure against cross-site scripting"], "correct" => 1],
  ["q" => "What is the primary purpose of a brand's primary color in a UI?", "options" => ["To style every text block on the screen", "To establish brand identity and draw attention to major actions", "To serve as the default page background", "To style error validation messages"], "correct" => 1],
  ["q" => "What is the value of negative space (white space) in layout design?", "options" => ["It is wasted space that should be filled", "It reduces page load time", "It creates visual breathing room and improves user focus", "It is required for SQL index optimization"], "correct" => 2],
  ["q" => "What is card sorting primarily used for by UX researchers?", "options" => ["Creating responsive color palettes", "Designing and testing a website's information architecture", "Arranging database schemas", "Testing button hover animations"], "correct" => 1],
  ["q" => "What is the purpose of an empathy map in UX design?", "options" => ["To map the user's geographical location", "To structure database tables for user roles", "To understand what users say, think, do, and feel during product interactions", "To design interactive navigation layouts"], "correct" => 2]
];

// ============================================================================
// GENERAL APTITUDE - 30 questions
// ============================================================================
$all_questions["General Aptitude"] = [
  ["q" => "What is the time complexity of searching in a balanced Binary Search Tree (BST)?", "options" => ["O(1)", "O(log n)", "O(n)", "O(n log n)"], "correct" => 1],
  ["q" => "Which protocol is used for secure encrypted communication over a computer network?", "options" => ["HTTP", "HTTPS", "FTP", "SMTP"], "correct" => 1],
  ["q" => "What is the primary role of an Operating System's Kernel?", "options" => ["Displaying user interfaces", "Managing system hardware and software resources", "Compiling source code", "Encrypting user passwords"], "correct" => 1],
  ["q" => "Which data structure operates on a Last-In, First-Out (LIFO) basis?", "options" => ["Queue", "Stack", "Linked List", "Tree"], "correct" => 1],
  ["q" => "Which data structure operates on a First-In, First-Out (FIFO) basis?", "options" => ["Queue", "Stack", "Hash Table", "Graph"], "correct" => 0],
  ["q" => "Which HTTP method is typically used to request data from a specified resource?", "options" => ["GET", "POST", "PUT", "DELETE"], "correct" => 0],
  ["q" => "What does the acronym 'DNS' stand for?", "options" => ["Digital Network Security", "Domain Name System", "Distributed Node System", "Database Navigation Server"], "correct" => 1],
  ["q" => "What is the mathematical definition of a prime number?", "options" => ["An even number divisible by 2", "A number divisible by any integer", "A whole number greater than 1 whose only divisors are 1 and itself", "A number that can be expressed as a fraction"], "correct" => 2],
  ["q" => "Find the next term in the logical sequence: 2, 4, 8, 16, ...", "options" => ["20", "24", "32", "64"], "correct" => 2],
  ["q" => "If worker A completes a job in 6 days and worker B completes it in 12 days, how long will they take to finish the job together?", "options" => ["3 days", "4 days", "8 days", "9 days"], "correct" => 1],
  ["q" => "What is the average of the first five prime numbers (2, 3, 5, 7, 11)?", "options" => ["4.8", "5.0", "5.6", "6.2"], "correct" => 2],
  ["q" => "What is the size of an IPv4 address in bits?", "options" => ["16 bits", "32 bits", "64 bits", "128 bits"], "correct" => 1],
  ["q" => "What does the acronym 'CPU' stand for?", "options" => ["Central Processing Unit", "Computer Process Utility", "Core Programming Unit", "Control Path Unit"], "correct" => 0],
  ["q" => "What is a fundamental difference between RAM and a Hard Drive?", "options" => ["RAM is permanent, Hard Drive is temporary", "RAM is volatile (loses data on power off), Hard Drive is non-volatile", "RAM is much slower than a Hard Drive", "RAM holds more storage capacity than a Hard Drive"], "correct" => 1],
  ["q" => "Which formula correctly represents the relationship between Speed, Distance, and Time?", "options" => ["Speed = Distance / Time", "Speed = Distance * Time", "Speed = Time / Distance", "Speed = Distance + Time"], "correct" => 0],
  ["q" => "Find the odd one out among the following: HTML, CSS, JavaScript, SQL.", "options" => ["HTML", "CSS", "JavaScript", "SQL"], "correct" => 3],
  ["q" => "What is the base-10 (decimal) equivalent of the binary number 1010?", "options" => ["6", "10", "12", "15"], "correct" => 1],
  ["q" => "Which of the following is a classic process scheduling algorithm used by operating systems?", "options" => ["Binary Search", "Dijkstra's Algorithm", "Round Robin", "Quick Sort"], "correct" => 2],
  ["q" => "Which Git command is used to save local changes in a temporary shelf, restoring the working directory?", "options" => ["git save", "git stash", "git commit", "git push"], "correct" => 1],
  ["q" => "Which application-layer protocol is standard for sending electronic mail over the Internet?", "options" => ["FTP", "SMTP", "IMAP", "HTTP"], "correct" => 1],
  ["q" => "What is the worst-case time complexity of the Bubble Sort algorithm?", "options" => ["O(n)", "O(n log n)", "O(n^2)", "O(2^n)"], "correct" => 2],
  ["q" => "Which logical operation returns true if and only if exactly one of its inputs is true?", "options" => ["AND", "OR", "XOR", "NAND"], "correct" => 2],
  ["q" => "Which of the following conditions must hold simultaneously for a system deadlock to occur?", "options" => ["Mutual Exclusion & Hold/Wait", "No Preemption", "Circular Wait", "All of the above"], "correct" => 3],
  ["q" => "Which of the following is a standard software development lifecycle methodology?", "options" => ["Waterfall-only", "Agile", "Linear Programming", "Object Oriented Design"], "correct" => 1],
  ["q" => "How many Megabytes (MB) are in 1 Gigabyte (GB) in standard binary measurement?", "options" => ["1000 MB", "1024 MB", "1048 MB", "1200 MB"], "correct" => 1],
  ["q" => "What type of cryptography uses the exact same key for both encryption and decryption?", "options" => ["Symmetric Cryptography", "Asymmetric Cryptography", "Hashing Algorithms", "Public Key Infrastructure"], "correct" => 0],
  ["q" => "What is a primary characteristic of a primary key in a database table?", "options" => ["It must contain foreign references", "It can contain null values", "It must be unique and cannot be null", "It must be a numerical auto-increment type"], "correct" => 2],
  ["q" => "What is the primary difference between a Compiler and an Interpreter?", "options" => ["A compiler translates the entire source code into machine code at once, while an interpreter translates it line by line", "A compiler is slower at run-time than an interpreter", "An interpreter is used only for styling markup languages", "A compiler does not check for syntax errors"], "correct" => 0],
  ["q" => "What does 'MVC' stand for in software architecture?", "options" => ["Main Vector Core", "Metadata Validation Center", "Model-View-Controller", "Method Variable Compiler"], "correct" => 2],
  ["q" => "In formal logic, if 'All A are B' and 'All B are C', which of the following is a valid conclusion?", "options" => ["All A are C", "All C are A", "No A are C", "Some C are not A"], "correct" => 0]
];
